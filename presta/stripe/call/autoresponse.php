<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */

use Stripe\Exception\ApiErrorException;

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('presta/stripe/inc/stripe');

/**
 * Gerer les webhooks Stripe
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_stripe_call_autoresponse_dist($config){

	include_spip('inc/bank');
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	list($event, $erreur, $erreur_code) = stripe_retrieve_event($config);

	$inactif = "";
	if (!$config['actif']){
		$inactif = "(inactif) ";
	}

	if ($erreur or $erreur_code){
		spip_log('call_autoresponse ' . $inactif . ': ' . "$erreur_code - $erreur", $mode . _LOG_ERREUR);
		http_response_code(400); // PHP 5.4 or greater
		exit();
	} else {
		$res = stripe_dispatch_event($config, $event);
	}

	include_spip('inc/headers');
	http_response_code(200); // No Content
	header("Connection: close");
	if ($res){
		return $res;
	}
	exit;

}


function stripe_dispatch_event($config, $event, $auto = 'auto'){
	$mode = $config['presta'] . $auto;
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	if (!$event){
		spip_log("call_{$auto}response : event NULL", $mode . _LOG_ERREUR);
		return null;
	}

	$type = $event->type;
	$type = preg_replace(',\W,', '_', $type);

	if (function_exists($f = "stripe_webhook_$type")
		or function_exists($f = $f . '_dist')){
		spip_log("call_{$auto}response : event $type => $f()", $mode . _LOG_DEBUG);
		$res = $f($config, $event);
		spip_log("call_{$auto}response : $f() = " . json_encode($res), $mode . _LOG_DEBUG);
		// loger le debug si echec
		if ($res === false) {
			spip_log($event, "stripe_db" . _LOG_DEBUG);
		}
	} else {
		spip_log("call_{$auto}response : event $type - $f not existing", $mode . _LOG_DEBUG);
		$res = null;
		spip_log($event, "stripe_db" . _LOG_ERREUR);
	}

	return $res;
}

function stripe_retrieve_event($config, $auto = 'auto'){
	// charger l'API Stripe avec la cle
	stripe_init_api($config);

	$event = null;

	$mode = $config['presta'] . $auto;
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$erreur = $erreur_code = '';

	// methode securisee par une cle secrete partagee
	// You can find your endpoint's secret in your webhook settings
	$key_webhook_secret = (($config['mode_test']) ? 'WEBHOOK_SECRET_KEY_test' : 'WEBHOOK_SECRET_KEY');
	if (isset($config[$key_webhook_secret]) and $config[$key_webhook_secret]){
		$endpoint_secret = $config[$key_webhook_secret];
		$payload = @file_get_contents('php://input');
		$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

		try {
			\Stripe\WebhookSignature::verifyHeader($payload, $sig_header, $endpoint_secret, 300);
			$event = \Stripe\Webhook::constructEvent(
				$payload, $sig_header, $endpoint_secret
			);
		} catch (\Stripe\Exception\SignatureVerificationException $e) {
			$erreur = $e->getMessage();
			// Invalid signature
			spip_log("erreur Webhook stripe_retrieve_event \Stripe\Exception\SignatureVerificationException: $erreur", $mode . _LOG_ERREUR);
			http_response_code(400); // PHP 5.4 or greater
			exit();
		} catch (\Stripe\Exception\UnexpectedValueException $e) {
			$erreur = $e->getMessage();
			// Invalid payload
			spip_log("erreur Webhook stripe_retrieve_event \Stripe\Exception\UnexpectedValueException: $erreur", $mode . _LOG_ERREUR);
			http_response_code(400); // PHP 5.4 or greater
			exit();
		} catch (\Exception $e) {
			$erreur = $e->getMessage();
			// Invalid signature
			spip_log("erreur Webhook stripe_retrieve_event \Exception: $erreur", $mode . _LOG_ERREUR);
			http_response_code(400); // PHP 5.4 or greater
			exit();
		}

	} else {
		// LEGACY : assurer la continuite de fonctionnement si la WEBHOOK_SECRET_KEY n'a pas ete configuree
		// Retrieve the request's body and parse it as JSON
		$input = @file_get_contents("php://input");
		$event_json = json_decode($input);
		$event_id = $event_json->id;

		try {
			// $event_id = 'evt_194CExB63f1NFl4k4qNLVNiS'; // debug
			// Verify the event by fetching it from Stripe
			$event = \Stripe\Event::retrieve($event_id);

		} catch (ApiErrorException $e) {
			if ($body = $e->getJsonBody()){
				$err = $body['error'];
				list($erreur_code, $erreur) = stripe_error_code($err);
			} else {
				$erreur = $e->getMessage();
				$erreur_code = 'error';
			}
			spip_log("erreur \Stripe\Event::retrieve($event_id): $erreur", $mode . _LOG_ERREUR);
		}
	}

	return [$event, $erreur, $erreur_code];
}


/**
 * Traitement des events
 * checkout_session.completed
 * checkout_session.expired
 * @param $config
 * @param $event
 * @return bool
 */
function _stripe_webhook_checkout_session_result($config, $event){
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$response = array();
	$session = $event->data->object;
	// il faut recuperer $charge, pay_id et abo_uid, creer un id_transaction
	if ($session->object=="checkout.session"
	  and in_array($session->payment_status, ['paid', 'unpaid'])){
		$response['checkout_session_id'] = $session->id;
		if (!empty($session->payment_intent)){
			$response['payment_id'] = $session->payment_intent;
		}
		if (!empty($session->subscription)){
			$response['abo_uid'] = $session->subscription;
		}
		if (!empty($session->customer)){
			$response['pay_id'] = $session->customer;
		}
		if (!empty($session->locale)) {
			$response['lang'] = $session->locale;
		}

		if (
			(!empty($session->success_url) and $url = $session->success_url)
			or (!empty($session->cancel_url) and $url = $session->cancel_url)
		){
			// get id_transaction & transaction_hash from success_url if valid for this website (case of multiples webhooks)
			$qs = explode('?', $url);
			$qs = end($qs);
			parse_str($qs, $c);
			// verifier la signature
			$r = bank_response_simple($config['presta'] . ((isset($config['mode_test']) and $config['mode_test']) ? '_test' : ''), $c);
			if ($r===false){
				spip_log("Echec verification signature url ".$url . " ".json_encode($c), $mode . _LOG_ERREUR);
				return false;
			}

			$response = array_merge($r, $response);
		}
	}

	spip_log($event, "stripe_db");

	if ((!empty($response['payment_id']) or !empty($response['abo_uid']))
		and !empty($response['id_transaction'])
		and !empty($response['transaction_hash'])){
		$call_response = charger_fonction('response', 'presta/stripe/call');
		$res = $call_response($config, $response);
		return $res;
	}

	return false;
}


/**
 * Callback called after a checkout payment (paiement simple)
 * @param $config
 * @param $event
 * @return bool
 */
function stripe_webhook_checkout_session_completed_dist($config, $event){
	return _stripe_webhook_checkout_session_result($config, $event);
}

/**
 * Callback called after a checkout expired
 * @param $config
 * @param $event
 * @return bool
 */
function stripe_webhook_checkout_session_expired_dist($config, $event) {
	return _stripe_webhook_checkout_session_result($config, $event);
}


/**
 * @param $config
 * @param $event
 * @return bool|array
 */
function stripe_webhook_customer_subscription_created_dist($config, $event) {
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	// TODO ?
	return false;
}

function stripe_webhook_customer_subscription_deleted_dist($config, $event) {
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$subscription = $event->data->object;
	// il faut recuperer abo_uid, pour resilier l'abonnement
	if ($subscription->object=="subscription"){
		$abo_uid = $subscription->id;

		if ($subscription->customer){
			$pay_id = $subscription->customer;
		}

		if ($resilier = charger_fonction('resilier', 'abos', true)){
			$options = array(
				'notify_bank' => false, // pas la peine : stripe a deja resilie l'abo vu paiement refuse
				'immediat' => true,
				'graceful' => true, // uniquement si abo pas deja résilié, pour ne pas faire quoi que ce soit si c'est un feedback après résiliation par le plugin abos
				'message' => "[bank] Abonnement resilie par Stripe ",
				'erreur' => true,
			);
			$resilier("uid:$abo_uid", $options);
		}

	}

	return null;
}

/**
 * Payment succeed
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_invoice_payment_succeeded_dist($config, $event){
	return stripe_webhook_invoice_payment_result('payment_succeeded', $config, $event);
}



/**
 * Payment failed
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_invoice_payment_failed_dist($config, $event){
	return stripe_webhook_invoice_payment_result('payment_failed', $config, $event);
}


/**
 * Payment result : meme traitement pour succeed ou fail
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_invoice_payment_result($raison, $config, $event){
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$response = array();
	$invoice = $event->data->object;
	// il faut recuperer $charge, pay_id et abo_uid, creer ou retrouver un id_transaction
	if ($invoice->object=="invoice"){
		if ($invoice->subscription){
			$response['abo_uid'] = $invoice->subscription;
		}
		if ($invoice->customer){
			$response['pay_id'] = $invoice->customer;
		}
		if ($invoice->charge){
			$response['charge_id'] = $invoice->charge;
		}
		if ($invoice->billing_reason){
			// subscription_create ou subscription_cycle
			$response['billing_reason'] = $invoice->billing_reason;
		}
		if ($invoice->payment_intent){
			$response['payment_id'] = $invoice->payment_intent;
		}
		// retrouver le id_transaction grace aux metadata du product si possible, uniquement a la creation de la subscription
		// pour les renouvelleemnt on va creer une transaction
		if (!empty($response['billing_reason'])
		  and $response['billing_reason'] === 'subscription_create'
		  and $invoice->lines){
			foreach ($invoice->lines->data as $line) {
				if (!empty($line->price) and !empty($line->price->product)) {
					$product_id = $line->price->product;
					try {
						$product = \Stripe\Product::retrieve($product_id);
						spip_log("$raison: ".var_export($product,true), "stripe_db");
						if (!empty($product->metadata->id_transaction)
						  and !empty($product->metadata->transaction_hash)) {
							$response['id_transaction'] = $product->metadata->id_transaction;
							$response['transaction_hash'] = $product->metadata->transaction_hash;
							break;
						}
					} catch (ApiErrorException $e) {
						if ($body = $e->getJsonBody()){
							$err = $body['error'];
							list($erreur_code, $erreur) = stripe_error_code($err);
						} else {
							$erreur = $e->getMessage();
							$erreur_code = 'error';
						}
						spip_log("stripe_webhook_invoice_payment_result:$raison: Erreur #$erreur_code $erreur", $mode . _LOG_ERREUR);
					}

				}
			}
		}

		// un helper pour eviter de faire une alerte frauduleuse si on ne trouve pas le id_transaction sur une notif de non paiement...
		if (isset($invoice->paid)) {
			$response['paid'] = $invoice->paid;
		}
	}

	spip_log("$raison: ".var_export($event,true), "stripe_db");

	if (
		(isset($response['charge_id']) or isset($response['payment_id']))
		and isset($response['abo_uid'])
		and isset($response['pay_id'])){
		$call_response = charger_fonction('response', 'presta/stripe/call');
		$res = $call_response($config, $response);
		return $res;
	}

	return false;
}


/**
 * payment_intent_created : peut arriver independamment du checkout
 * peut arriver *apres* payment_intent.requires_action
 * il faut retrouver la transaction qui va avec
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_payment_intent_created_dist($config, $event){
	// TODO ?
	return false;
}


/**
 * payment_intent.payment_failed : peut arriver independamment du checkout
 * il faut retrouver la transaction qui va avec la mettre en echec
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_payment_intent_payment_failed_dist($config, $event){
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$payment_intent = $event->data->object;
	// il faut recuperer payment_id, pay_id et abo_uid, creer ou retrouver un id_transaction
	if ($payment_intent->object=="payment_intent"){
		if (!empty($payment_intent->id)
		  and !empty($payment_intent->created)
		  and !empty($payment_intent->customer)
		) {

			$date_payment = date('Y-m-d H:i:s', $payment_intent->created);
			$customer_id = $payment_intent->customer;
			$payment_intent_id = $payment_intent->id;

			if ($res = stripe_retrouve_transaction_par_payment_et_customer($config, $customer_id, $payment_intent_id, $date_payment)) {
				[$transaction, $checkout_session] = $res;
				// enregistrer l'echec du paiement sur la transaction
				$response = [
					'payment_id' => $payment_intent_id,
					'pay_id' => $customer_id,
					'id_transaction' => $transaction['id_transaction'],
					'transaction_hash' => $transaction['transaction_hash'],
				];
				$call_response = charger_fonction('response', 'presta/stripe/call');
				$res = $call_response($config, $response);
				return $res;
			}
		}
	}

	return false;
}


/**
 * payment_intent.succeeded : arrive avant le checkout.completed
 * il faut retrouver la transaction qui va avec et enregistrer le succes du paiement si possible
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_payment_intent_succeeded_dist($config, $event){
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$payment_intent = $event->data->object;
	// il faut recuperer payment_id, pay_id et abo_uid, creer ou retrouver un id_transaction
	if ($payment_intent->object=="payment_intent"){
		if (!empty($payment_intent->id)
		  and !empty($payment_intent->created)
		  and !empty($payment_intent->customer)
		) {

			$date_payment = date('Y-m-d H:i:s', $payment_intent->created);
			$customer_id = $payment_intent->customer;
			$payment_intent_id = $payment_intent->id;

			if ($res = stripe_retrouve_transaction_par_payment_et_customer($config, $customer_id, $payment_intent_id, $date_payment)) {
				[$transaction, $checkout_session] = $res;
				// enregistrer le succes du paiement sur la transaction
				$response = [
					'payment_id' => $payment_intent_id,
					'pay_id' => $customer_id,
					'id_transaction' => $transaction['id_transaction'],
					'transaction_hash' => $transaction['transaction_hash'],
				];
				if (!empty($session->subscription)){
					$response['abo_uid'] = $session->subscription;
				}
				if (!empty($session->locale)) {
					$response['lang'] = $session->locale;
				}
				$call_response = charger_fonction('response', 'presta/stripe/call');
				$res = $call_response($config, $response);
				return $res;
			}
		}
	}

	return false;
}

/**
 * payment_intent.requires_action : peut arriver independamment du checkout
 * peut arriver *avant* payment_intent.created
 * il faut retrouver la transaction qui va avec
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_payment_intent_requires_action_dist($config, $event){
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$response = array();
	$payment_intent = $event->data->object;
	// il faut recuperer $charge, pay_id et abo_uid, creer ou retrouver un id_transaction
	if ($payment_intent->object=="payment_intent"){
		if (!empty($payment_intent->id)
		  and !empty($payment_intent->created)
		  and !empty($payment_intent->customer)
		) {

			$date_payment = date('Y-m-d H:i:s', $payment_intent->created);
			$customer_id = $payment_intent->customer;
			$payment_intent_id = $payment_intent->id;

			if ($res = stripe_retrouve_transaction_par_payment_et_customer($config, $customer_id, $payment_intent_id, $date_payment)) {
				[$transaction, $checkout_session] = $res;
				$id_transaction = $transaction['id_transaction'];
				spip_log("stripe_webhook_payment_requires_action_dist: payment_intent.requires_action sur transaction #$id_transaction", $mode . _LOG_DEBUG);
				// doit on faire quelque chose ? a priori non, c'est purement informatif
			}
		}
	}

	return false;
}
