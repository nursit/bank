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

/* Paybox ------------------------------------------------------------------ */

// pour forcer le mode test :
// define('_PAYBOX_SANDBOX',true);


/* ------------------------------------------------------------------------- */

/**
 * Donnees de test :
 * utiliser les donnees de la boutique avec le mode test actif
 * Back-office : https://preprod-admin.paybox.com/ ou https://admin.paybox.com/
 *
 *
 * Paybox pour CA
 * PBX_IDENTIFIANT : 3
 * PBX_SITE : 1999888
 * PBX_RANG : 98
 *
 * Certificat :
 * -----BEGIN PUBLIC KEY-----
 * MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDe+hkicNP7ROHUssGNtHwiT2Ew
 * HFrSk/qwrcq8v5metRtTTFPE/nmzSkRnTs3GMpi57rBdxBBJW5W9cpNyGUh0jNXc
 * VrOSClpD5Ri2hER/GcNrxVRP7RlWOqB1C03q4QYmwjHZ+zlM4OUhCCAtSWflB4wC
 * Ka1g88CjFwRw/PB9kwIDAQAB
 * -----END PUBLIC KEY-----
 */

function paybox_lister_cartes_config($c){
	include_spip('inc/bank');
	include_spip("presta/paybox/inc/paybox");
	$config = array(
		'presta' => 'paybox',
		'type' => isset($c['type']) ? $c['type'] : 'acte',
	);
	return paybox_available_cards($config);
}