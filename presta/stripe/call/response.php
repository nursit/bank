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
 * Verifier le statut d'une transaction lors du retour de l'internaute
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_stripe_call_response_dist($config, $response=null){

	include_spip('inc/bank');
	$mode = $config['presta'];

	// recuperer la reponse en post et la decoder, en verifiant la signature
	if (!$response) {
		$response = bank_response_simple($mode);
	}

	// Stripe token
	$token = '';
	if (isset($_REQUEST['stripeToken'])){
		$token = $_REQUEST['stripeToken'];
	}

	if (!$response or !$token) {
		return array(0,false);
	}

	$response['token'] = $token;
	if (isset($_REQUEST['stripeTokenType'])){
		$response['token_type'] = $_REQUEST['stripeTokenType'];
	}


	// depouillement de la transaction
	list($id_transaction,$success) =  stripe_traite_reponse_transaction($config, $response);

	if ($response['abo'] AND $id_transaction) {


	}
	return array($id_transaction,$success);	

}

