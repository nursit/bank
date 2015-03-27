<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * Olivier Tétard
 * (c) 2014 - Distribue sous licence GNU/GPL
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
function presta_virement_payer_acte_dist($config, $id_transaction, $transaction_hash, $options=array()){

	$contexte = array(
		'action' => bank_url_api_retour($config,'response'),
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
		'attente_mode' => _request('attente_mode'),
		'config' => $config,
	);

	$contexte = array_merge($options, $contexte);

	return recuperer_fond('presta/virement/payer/acte', $contexte, array('ajax'=>true));
}
