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
 * SystemPay est une variante de PayZen et repose sur l'implementation de Payzen
 * il est presente comme un prestataire separe pour une meilleure lisibilite
 *
 * @deprecated
 */
function presta_systempay_call_request_dist($id_transaction, $transaction_hash, $config = array(), $action = "PAYMENT", $options = array()){
	$call_request = charger_fonction('request', 'presta/payzen/call');
	return $call_request($id_transaction, $transaction_hash, $config, $action, $options);
}
