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

function presta_cmcic_payer_acte_dist($id_transaction,$transaction_hash, $titre=''){

	include_spip('inc/bank');
	$config = bank_config("cmcic");

	$call_request = charger_fonction('request','presta/cmcic/call');
	$contexte = $call_request($id_transaction,$transaction_hash,$config);
	$contexte['title'] = $titre;

	include_spip('inc/cmcic');
	$contexte['sandbox'] = cmcic_is_sandbox($config);

	return recuperer_fond('presta/cmcic/payer/acte',$contexte);
}

