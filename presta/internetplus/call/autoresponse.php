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


function presta_internetplus_call_autoresponse_dist($mode = "internetplus"){

	include_spip('inc/bank');
	$config = bank_config($mode,true);

	$responder = charger_fonction('responder','presta/internetplus/inc');
	list($uoid,$resil)=$responder();

	if ($uoid
		AND $resil
	  AND $resilier = charger_fonction('resilier','abos',true)){
		$resilier("uid:$uoid",array('immediat'=>reset($resil),'message'=>end($resil)));
	}

	// peu importe, on die() apres car c'est une autoresponse silencieuse
	return array(0,false);
}