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

function presta_paybox_payer_abonnement_dist($id_transaction,$transaction_hash){

	$call_request = charger_fonction('request','presta/paybox/call');
	echo $call_request($id_transaction,$transaction_hash,1,array('CB','VISA','EUROCARD_MASTERCARD'));
	
	return recuperer_fond('presta/paybox/formulaires/abonnement',array('form'=>$form));
}

?>