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

function presta_paybox_payer_abonnement_dist($id_transaction, $transaction_hash, $titre=''){

	include_spip('inc/bank');
	$config = bank_config("paybox",true);

	$call_request = charger_fonction('request','presta/paybox/call');
	$contexte = $call_request($id_transaction,$transaction_hash,$config);
	$contexte['title'] = $titre;

	$contexte['sandbox'] = (paybox_is_sandbox($config)?' ':'');

	return recuperer_fond('presta/paybox/payer/abonnement',$contexte);
}

