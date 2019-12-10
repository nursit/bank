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
function presta_cmcic_payer_acte_dist($config, $id_transaction, $transaction_hash, $options = array()){

	$call_request = charger_fonction('request', 'presta/cmcic/call');
	$contexte = $call_request($id_transaction, $transaction_hash, $config);
	if (!$contexte){
		return "";
	}

	include_spip('inc/cmcic');
	$contexte['sandbox'] = (cmcic_is_sandbox($config) ? ' ' : '');
	$contexte['logo'] = bank_trouver_logo("cmcic", "CARD.gif");
	$contexte['config'] = $config;

	$contexte = array_merge($options, $contexte);

	return recuperer_fond('presta/cmcic/payer/acte', $contexte);
}

