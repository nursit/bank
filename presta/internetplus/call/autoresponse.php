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

/**
 * @param array $config
 * @return array
 */
function presta_internetplus_call_autoresponse_dist($config){

	include_spip('inc/bank');
	$mode = $config['presta'];

	$responder = charger_fonction('responder','presta/internetplus/inc');
	list($uoid,$resil)=$responder();

	if ($uoid
		AND $resil
	  AND $resilier = charger_fonction('resilier','abos',true)){
		$options = array(
			'notify_bank' => false, // pas la peine : abo deja resilie vu paiement refuse
			'immediat' => reset($resil),
			'message' => end($resil),
			'erreur' => true
		);
		$resilier("uid:$uoid",$options);
	}

	// peu importe, on die() apres car c'est une autoresponse silencieuse
	return array(0,false);
}