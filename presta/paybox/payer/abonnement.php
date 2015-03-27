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
function presta_paybox_payer_abonnement_dist($config, $id_transaction, $transaction_hash, $options=array()){


	$call_request = charger_fonction('request','presta/paybox/call');
	$contexte = $call_request($id_transaction,$transaction_hash,$config);

	$contexte['sandbox'] = (paybox_is_sandbox($config)?' ':'');

	$contexte = array_merge($options,$contexte);

	return recuperer_fond('presta/paybox/payer/abonnement',$contexte);
}

