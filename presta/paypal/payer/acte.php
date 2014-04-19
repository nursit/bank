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

	return recuperer_fond('presta/paypal/payer/acte',
		array(
			'url_return' => generer_url_action('bank_response', 'bankp=paypal', true, true),
			'url_notify' => generer_url_action('bank_autoresponse', 'bankp=paypal', true, true),
			'url_cancel' => generer_url_action('bank_cancel', 'bankp=paypal', true, true),
			'id_transaction'=>$id_transaction,
			'transaction_hash'=>$transaction_hash,
			'sandbox' => _PAYPAL_SANDBOX,
		)
	);
}

?>