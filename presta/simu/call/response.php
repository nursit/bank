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

include_spip('inc/date');

/**
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 * 
 * @param string $mode
 * @param null|array $response
 * @return array
 */
function presta_simu_call_response_dist($mode="simu", $response=null){

	include_spip('inc/bank');
	$config = bank_config($mode);

	// recuperer la reponse en post et la decoder, en verifiant la signature
	if (!$response)
		$response = bank_response_simple($mode);

		// est-ce une simulation d'echec ?
	if (_request('status')=='fail'){
		$response['fail'] = "Simulation echec paiement";
	}

	// generer un numero d'abonne simule si besoin
	if (_request('abo')){
		$response['abo_uid'] = substr(md5($response['id_transaction']."-".time()),0,10);
	}

	return bank_simple_call_response($mode, $response);
}
