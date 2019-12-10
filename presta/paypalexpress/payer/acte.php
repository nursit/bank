<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * @param array $config
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param array $options
 * @return array|string
 */
function presta_paypalexpress_payer_acte_dist($config, $id_transaction, $transaction_hash, $options=array()){

	include_spip('presta/paypalexpress/inc/paypalexpress');

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
		'url_confirm' => parametre_url(self(),'confirm','oui'),
		'sandbox' => paypalexpress_is_sandbox($config)?' ':'',
		'logo' => bank_trouver_logo('paypal','PAYPAL.gif'),
		'config' => $config,
	);

	$contexte = array_merge($options, $contexte);

	return recuperer_fond('presta/paypalexpress/payer/acte', $contexte);
}

?>