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
 * SystemPay est une variante de PayZen et repose sur l'implementation de Payzen
 * il est presente comme un prestataire separe pour une meilleure lisibilite
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_systempay_call_response_dist($config, $response = null){

	$call_response = charger_fonction("response", "presta/payzen/call");
	return $call_response($config, $response);
}
