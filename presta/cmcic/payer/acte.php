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

	$call_request = charger_fonction('request','presta/cmcic/call');
	$contexte = $call_request($id_transaction,$transaction_hash);
	$contexte['title'] = $titre;

	$contexte['sandbox'] = (CMCIC_TEST?' ':'');

	return recuperer_fond('presta/cmcic/payer/acte',$contexte);
}

?>
