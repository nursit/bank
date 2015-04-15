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


/**
 * @param array $config
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param array $options
 * @return array|string
 */
function presta_paypal_payer_acte_dist($config, $id_transaction, $transaction_hash, $options=array()){

	include_spip('presta/paypal/inc/paypal');

	$contexte = array(
		'action' => paypal_url_serveur($config),
		'url_return' => bank_url_api_retour($config,"response"),
		'url_notify' => bank_url_api_retour($config,"autoresponse"),
		'url_cancel' => bank_url_api_retour($config,"cancel"),
		'id_transaction'=>$id_transaction,
		'transaction_hash'=>$transaction_hash,
		'sandbox' => paypal_is_sandbox($config),
		'logo' => bank_trouver_logo('paypal','PAYPAL.gif'),
		'config' => $config,
	);

	$contexte = array_merge($options, $contexte);

	return recuperer_fond('presta/paypal/payer/acte', $contexte);
}

?>