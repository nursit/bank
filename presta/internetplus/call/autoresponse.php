<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2014 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;


function presta_internetplus_call_autoresponse_dist(){

	$wha_responder = charger_fonction('responder','presta/internetplus/inc');
	list($uoid,$resil)=$wha_responder();

	if ($uoid AND $resil){
		$resilier = charger_fonction('resilier','abos');
		$resilier("uid:$uoid",array('immediat'=>reset($resil),'message'=>end($resil)));
	}

	// peu importe, on die() apres car c'est une autoresponse silencieuse
	return array(0,false);
}