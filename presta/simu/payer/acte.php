<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function presta_simu_payer_acte_dist($id_transaction,$transaction_hash){

	include_spip('inc/bank');
	$config = bank_config("simu");

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
	);
	$contexte['sign'] = bank_sign_response_simple('simu', $contexte);

	// url action
	$action = bank_url_api_retour($config,'response');
	foreach($contexte as $k=>$v){
		$action = parametre_url($action,$k,$v);
	}
	$contexte['action'] = $action;

	return recuperer_fond('presta/simu/payer/acte',$contexte);

}
