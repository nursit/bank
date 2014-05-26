<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

/* Paybox ------------------------------------------------------------------ */

define('_PAYBOX_SANDBOX',
	(	   (isset($GLOBALS['config_bank_paiement']['config_paybox']['mode_test']) AND $GLOBALS['config_bank_paiement']['config_paybox']['mode_test'])
	  OR (isset($GLOBALS['config_bank_paiement']['config_abo_paybox']['mode_test']) AND $GLOBALS['config_bank_paiement']['config_abo_paybox']['mode_test'])
	)?true:false);

/**
 * Constantes pour paybox
 * plateforme de test
 * 
 */
if (!defined('_PAYBOX_URL'))
	define('_PAYBOX_URL',
		_PAYBOX_SANDBOX?
			"https://preprod-tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi"
		:
			"https://tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi");
if (!defined('_PAYBOX_URL_RESIL'))
	define('_PAYBOX_URL_RESIL',
		_PAYBOX_SANDBOX?
			"https://preprod-tpeweb.paybox.com/cgi-bin/ResAbon.cgi"
		:
			"https://tpeweb.paybox.com/cgi-bin/ResAbon.cgi");

// Appels demande de paiement DIRECTPLUS
if (!defined('_PAYBOX_DIRECT_URL'))
	define('_PAYBOX_DIRECT_URL',"https://ppps.paybox.com/PPPS.php");

/* ------------------------------------------------------------------------- */

/**
 * Donnees de test :
 * utiliser les donnees de la boutique avec le mode test actif
 * Back-office : https://preprod-admin.paybox.com/ ou https://admin.paybox.com/
 *

Paybox pour CA
PBX_IDENTIFIANT : 3
PBX_SITE : 1999888
PBX_RANG : 98

Certificat :
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDe+hkicNP7ROHUssGNtHwiT2Ew
HFrSk/qwrcq8v5metRtTTFPE/nmzSkRnTs3GMpi57rBdxBBJW5W9cpNyGUh0jNXc
VrOSClpD5Ri2hER/GcNrxVRP7RlWOqB1C03q4QYmwjHZ+zlM4OUhCCAtSWflB4wC
Ka1g88CjFwRw/PB9kwIDAQAB
-----END PUBLIC KEY-----


*/


//_PAYBOX_DIRECT_CLE
