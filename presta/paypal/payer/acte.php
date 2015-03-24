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

function presta_paypal_payer_acte_dist($id_transaction,$transaction_hash){

	include_spip('inc/bank');
	$config = bank_config("paypal");

	include_spip('presta/paypal/inc/paypal');
	return recuperer_fond('presta/paypal/payer/acte',
		array(
			'action' => paypal_url_serveur($config),
			'url_return' => bank_url_api_retour($config,"response"),
			'url_notify' => bank_url_api_retour($config,"autoresponse"),
			'url_cancel' => bank_url_api_retour($config,"cancel"),
			'id_transaction'=>$id_transaction,
			'transaction_hash'=>$transaction_hash,
			'sandbox' => paypal_is_sandbox($config),
			'config' => $config,
		)
	);
}

?>