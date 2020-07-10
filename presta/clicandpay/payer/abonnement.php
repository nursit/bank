<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * Lyra
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')){
	return;
}


/**
 * @param array $config
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param array $options
 * @return array|string
 */
function presta_clicandpay_payer_abonnement_dist($config, $id_transaction, $transaction_hash, $options = array()){

	$call_request = charger_fonction('request', 'presta/systempay/call');

	// Tip : pour tester les workflow de paiement abonnement decomposes avec les CB
	// utiliser ici REGISTER_SUBSCRIBE au lieu de REGISTER_PAY_SUBSCRIBE
	// cela permet d'avoir un premier hit sans paiement puis un hit du paiement dans l'heure (arrivera 13j apres en SEPA)
	$contexte = $call_request($id_transaction, $transaction_hash, $config, "REGISTER_PAY_SUBSCRIBE");

	$contexte['sandbox'] = ($config['mode_test'] ? ' ' : '');
	$contexte['config'] = $config;

	$contexte = array_merge($options, $contexte);

	return recuperer_fond('presta/clicandpay/payer/abonnement', $contexte);
}
