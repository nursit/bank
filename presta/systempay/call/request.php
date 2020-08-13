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


/*
 * Generer le contexte pour le formulaire de requete de paiement
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @deprecated
 */
function presta_systempay_call_request_dist($id_transaction, $transaction_hash, $config = array(), $action = "PAYMENT", $options = array()){
	$call_request = charger_fonction('request', 'presta/payzen/call');
	return $call_request($id_transaction, $transaction_hash, $config = array(), $action = "PAYMENT", $options = array());
}