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

/* Systempay  ----------------------------------------------------------- */

function systempay_lister_cartes_config($c){
	$config = array(
		'presta' => 'systempay',
		'type' => isset($c['type']) ? $c['type'] : 'acte',
		'service' => isset($c['service']) ? $c['service'] : 'cyberplus'
	);
	include_spip("presta/payzen/inc/payzen");
	return payzen_available_cards($config);
}
