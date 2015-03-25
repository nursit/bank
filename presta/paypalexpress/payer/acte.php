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

function presta_paypalexpress_payer_acte_dist($id_transaction,$transaction_hash){

	include_spip('presta/paypalexpress/inc/paypalexpress');
	include_spip('inc/bank');
	$config = bank_config("paypalexpress");


	return recuperer_fond('presta/paypalexpress/payer/acte',
		array(
			'id_transaction' => $id_transaction,
			'transaction_hash' => $transaction_hash,
			'url_confirm' => parametre_url(self(),'confirm','oui'),
			'sandbox' => paypalexpress_is_sandbox($config)?' ':'',
			'config' => $config,
		)
	);
}

?>