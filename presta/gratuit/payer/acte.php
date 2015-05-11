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

function presta_gratuit_payer_acte_dist($config, $id_transaction,$transaction_hash, $options=array()){

	include_spip('inc/bank');
	$config = bank_config("gratuit");

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
	);
	$contexte['sign'] = bank_sign_response_simple("gratuit",$contexte);
	$contexte['action'] = bank_url_api_retour($config,"response");

	$contexte = array_merge($options,$contexte);

	return recuperer_fond('presta/gratuit/payer/acte',$contexte);
}

?>