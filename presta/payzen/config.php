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

/* PayZen  ----------------------------------------------------------- */


/**
 *
 * Payzen est fourni par LyraNetworks comme SystemPay dont il partage une grande partie du code
 *
 */

# Version du logiciel
if (!defined('_SYSTEMPAY_VERSION'))
	define("_SYSTEMPAY_VERSION", "V2");


function payzen_lister_cartes_config($abo=false){
	include_spip('inc/bank');
	$config = bank_config('payzen',$abo);
	// au premier coup, quand la config a pas encore ete enregistree service est vide
	if (!isset($config['service'])){
		$config['service'] = 'payzen';
	}

	include_spip("presta/systempay/inc/systempay");
	return systempay_available_cards($config);
}
