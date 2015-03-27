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

function presta_sips_payer_acte_dist($id_transaction, $transaction_hash, $titre=''){

	include_spip('inc/bank');
	$config = bank_config("sips");

	$call_request = charger_fonction('request','presta/sips/call');
	$contexte = $call_request($id_transaction,$transaction_hash,$config);

	$contexte['title'] = $titre;

	return recuperer_fond('presta/sips/payer/acte',$contexte);
}

?>