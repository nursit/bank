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

include_spip('presta/internetplus/inc/wha_services');

function presta_internetplus_payer_acte_dist($id_transaction,$transaction_hash){

	// veriffier que le montant est < 30EUR
	$montant = sql_getfetsel("montant","spip_transactions","id_transaction=".intval($id_transaction));
	if ($montant>=30)
		return "";


	$url_payer = wha_url_transaction($id_transaction,$transaction_hash,_WHA_MERCHANT_ID,_WHA_KEY_ID);
	return recuperer_fond('presta/internetplus/payer/acte',
		array(
			'id_transaction' => $id_transaction,
			'transaction_hash' => $transaction_hash,
			'url_payer' => $url_payer,
			'logo' => wha_logo_detecte_fai_visiteur(),
			'sandbox' => defined('_INTERNETPLUS_SANDBOX')?' ':'',
		)
	);
}