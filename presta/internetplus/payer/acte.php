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

include_spip('presta/internetplus/inc/wha_services');

function presta_internetplus_payer_acte_dist($config,$id_transaction,$transaction_hash){

	include_spip('inc/bank');

	// verifier que le montant est < 30EUR
	$montant = sql_getfetsel("montant","spip_transactions","id_transaction=".intval($id_transaction));
	if ($montant>=30){
		spip_log("Payer acte transaction #$id_transaction : montant non pris en charge " . $montant,"internetplus"._LOG_INFO_IMPORTANTE);
		return "";
	}

	$url_payer = wha_url_transaction($id_transaction,$transaction_hash,$config);
	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
		'url_payer' => $url_payer,
		'logo' => wha_logo_detecte_fai_visiteur(),
		'sandbox' => (wha_is_sandbox($config)?' ':''),
	);

	return recuperer_fond('presta/internetplus/payer/acte', $contexte);
}