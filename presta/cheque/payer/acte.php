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

function presta_cheque_payer_acte_dist($id_transaction,$transaction_hash){

	include_spip("inc/bank");
	$config = bank_config("cheque");

	$contexte = array(
		'action' => bank_url_api_retour($config,"response"),
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
		'attente_mode' => _request('attente_mode'),
		'config' => $config,
	);

	return recuperer_fond('presta/cheque/payer/acte', $contexte, array('ajax'=>true));
}

