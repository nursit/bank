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

/* Stripe  ----------------------------------------------------------- */



function stripe_lister_cartes_config($c, $cartes = true){
	include_spip('inc/bank');
	$config = array('presta' => 'stripe', 'type' => isset($c['type']) ? $c['type'] : 'acte');

	include_spip("presta/stripe/inc/stripe");
	$liste = stripe_available_cards($config);

	return $liste;
}

