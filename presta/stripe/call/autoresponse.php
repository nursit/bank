<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2015 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('presta/stripe/inc/stripe');

/**
 * Gerer les webhooks Stripe
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_stripe_call_autoresponse_dist($config) {

	include_spip('inc/bank');
	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']) $mode .= "_test";

	// charger l'API Stripe avec la cle
	stripe_init_api($config);

	// Retrieve the request's body and parse it as JSON
	$input = @file_get_contents("php://input");
	$event_json = json_decode($input);

	$erreur = $erreur_code = '';
	$res = false;
	try {
		// Verify the event by fetching it from Stripe
		$event = \Stripe\Event::retrieve($event_json->id);

		if ($event) {
			$type = $event->type;
			$type = preg_replace(',\W,','_', $type);
			if (function_exists($f = "stripe_webhook_$type")
			  or function_exists($f = $f . '_dist')) {
				spip_log("call_autoresponse : event $type => $f()", $mode.'auto'._LOG_DEBUG);
				$res = $f($config, $event);
			}
			else {
				spip_log("call_autoresponse : event $type - $f not existing", $mode.'auto'._LOG_DEBUG);
			}
		}

		// Do something with $event

	} catch (Exception $e) {
		if ($body = $e->getJsonBody()){
			$err  = $body['error'];
			list($erreur_code, $erreur) = stripe_error_code($err);
		}
		else {
			$erreur = $e->getMessage();
			$erreur_code = 'error';
		}
	}

	$inactif = "";
	if (!$config['actif']) {
		$inactif = "(inactif) ";
	}

	if ($erreur or $erreur_code) {
		spip_log('call_autoresponse '.$inactif.': '."$erreur_code - $erreur", $mode.'auto'._LOG_ERREUR);
	}

	include_spip('inc/headers');
	http_status(200); // No Content
	header("Connection: close");
	if ($res) {
		return $res;
	}
	exit;

}

/**
 * Payment succeed
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_invoice_payment_succeeded_dist($config, $event) {
	
	$response = array();
	// il faut recuperer $charge, pay_id et abo_uid, creer un id_transaction
	
	spip_log($event,"stripe_db");
	return false;
}

/**
 * Payment failed
 * @param array $config
 * @param object $event
 * @return bool|array
 */
function stripe_webhook_invoice_payment_failed_dist($config, $event) {
	spip_log($event,"stripe_db");
	return false;
}