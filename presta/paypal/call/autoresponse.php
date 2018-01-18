<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2018 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Verifier le statut d'une transaction lors du retour de l'internaute
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_paypal_call_autoresponse($config, $response=null){

	include_spip('inc/bank');
	include_spip('presta/paypal/inc/paypal');

	if (!$response){
		$response = paypal_get_response($config, true);
	}

	return paypal_traite_response($config, $response);
}

