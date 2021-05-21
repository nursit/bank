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

	// methode securisee par une cle secrete partagee
	// You can find your endpoint's secret in your webhook settings
	$key_webhook_secret = (($config['mode_test']) ? 'WEBHOOK_SECRET_KEY_test' : 'WEBHOOK_SECRET_KEY');
	if (isset($config[$key_webhook_secret]) and $config[$key_webhook_secret]){
		$endpoint_secret = $config[$key_webhook_secret];
		$payload = @file_get_contents('php://input');
		$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

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

		$erreur = $erreur_code = '';
		try {
			// $event_id = 'evt_194CExB63f1NFl4k4qNLVNiS'; // debug
			// Verify the event by fetching it from Stripe
			$event = \Stripe\Event::retrieve($event_id);

		} catch (Exception $e) {
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
 * Callback called after a checkout payment (paiement simple)
 * @param $config
 * @param $event
 * @return bool
 */
function stripe_webhook_checkout_session_completed_dist($config, $event){
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$response = array();
	$session = $event->data->object;
	// il faut recuperer $charge, pay_id et abo_uid, creer un id_transaction
	if ($session->object=="checkout.session"
	  and $session->payment_status == "paid"){
		$response['checkout_session_id'] = $session->id;
		if ($session->payment_intent){
			$response['payment_id'] = $session->payment_intent;
		}
		if ($session->subscription){
			$response['abo_uid'] = $session->subscription;
		}
		if ($session->customer){
			$response['pay_uid'] = $session->customer;
		}
		if ($session->locale) {
			$response['lang'] = $session->locale;
		}

		if ($session->success_url){
			// get id_transaction & transaction_hash from success_url if valid for this website (case of multiples webhooks)
			$qs = explode('?', $session->success_url);
			$qs = end($qs);
			parse_str($qs, $c);
			// verifier la signature
			$r = bank_response_simple($config['presta'] . ((isset($config['mode_test']) and $config['mode_test']) ? '_test' : ''), $c);
			if ($r===false){
				spip_log("Echec verification signature success_url ".$session->success_url . " ".json_encode($c), $mode . _LOG_ERREUR);
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

	return null;
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
			$pay_uid = $subscription->customer;
		}

		if ($resilier = charger_fonction('resilier', 'abos', true)){
			$options = array(
				'notify_bank' => false, // pas la peine : stripe a deja resilie l'abo vu paiement refuse
				'immediat' => true,
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
			$response['pay_uid'] = $invoice->customer;
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
			if (!isset($response['charge_id'])){
				try {
					$payment = \Stripe\PaymentIntent::retrieve($response['payment_id']);
					spip_log($payment, "stripe_db");
					if ($payment->charges
						and $payment->charges->data
						and $charge = end($payment->charges->data)){
						$response['charge_id'] = $charge->id;
					}
				} catch (Exception $e) {
					if ($body = $e->getJsonBody()){
						$err = $body['error'];
						list($erreur_code, $erreur) = stripe_error_code($err);
					} else {
						$erreur = $e->getMessage();
						$erreur_code = 'error';
					}
					spip_log("stripe_webhook_invoice_payment_succeeded_dist: Erreur #$erreur_code $erreur", $mode . _LOG_ERREUR);
				}
			}
		}
	}

	spip_log($event, "stripe_db");

	if (isset($response['charge_id'])
		and isset($response['abo_uid'])
		and isset($response['pay_uid'])){
		$call_response = charger_fonction('response', 'presta/stripe/call');
		$res = $call_response($config, $response);
		return $res;
	}

	return false;
}

/**
 * Payment failed
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_invoice_payment_failed_dist($config, $event){
	$mode = $config['presta'] . 'auto';
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$response = array();
	$invoice = $event->data->object;
	// il faut recuperer $charge, pay_id et abo_uid, creer un id_transaction
	if ($invoice->object=="invoice"){
		if ($invoice->subscription){
			$response['abo_uid'] = $invoice->subscription;
		}
		if ($invoice->customer){
			$response['pay_uid'] = $invoice->customer;
		}
		if ($invoice->charge){
			$response['charge_id'] = $invoice->charge;
		}
		if ($invoice->payment_intent){
			$response['payment_id'] = $invoice->payment_intent;
			if (!isset($response['charge_id'])){
				try {
					$payment = \Stripe\PaymentIntent::retrieve($response['payment_id']);
					if ($payment->charges
						and $payment->charges->data
						and $charge = end($payment->charges->data)){
						$response['charge_id'] = $charge->id;
					}
				} catch (Exception $e) {
					if ($body = $e->getJsonBody()){
						$err = $body['error'];
						list($erreur_code, $erreur) = stripe_error_code($err);
					} else {
						$erreur = $e->getMessage();
						$erreur_code = 'error';
					}
					spip_log("stripe_webhook_invoice_payment_succeeded_dist: Erreur #$erreur_code $erreur", $mode . _LOG_ERREUR);
				}
			}
		}
	}

	spip_log($event, "stripe_db");

	if (isset($response['charge_id'])
		and isset($response['abo_uid'])
		and isset($response['pay_uid'])){
		$call_response = charger_fonction('response', 'presta/stripe/call');
		$res = $call_response($config, $response);
		return $res;
	}

	return false;
}
