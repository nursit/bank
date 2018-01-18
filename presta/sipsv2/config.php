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

/* SIPS v2  ----------------------------------------------------------- */



function sipsv2_lister_cartes_config($c){
	$config = array(
		'presta'=>'sipsv2',
		'type'=>isset($c['type'])?$c['type']:'acte',
		'service'=>isset($c['service'])?$c['service']:'sogenactif'
	);
	include_spip("presta/sipsv2/inc/sipsv2");
	return sipsv2_available_cards($config);
}
