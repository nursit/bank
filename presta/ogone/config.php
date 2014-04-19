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

/* Ogone ------------------------------------------------------------------ */

/**
 * Constantes pour Ogone
 * plateforme de test
 * a personaliser dans mes_options pour activer la config en prod
 * define('_OGONE_URL',"https://secure.ogone.com/ncol/prod/orderstandard.asp ");
 *
 * les secrets _OGONE_CLE_SHA_IN et _OGONE_CLE_SHA_OUT doivent etre
 * personalises et declares dans l'interface d'admin de Ogone
 *
 */

// PSPID : il s'agit de l'identifiant unique de marchand fournit par Ogone
if (!defined('_OGONE_PSPID'))
	define('_OGONE_PSPID',$GLOBALS['config_bank_paiement']['config_ogone']['PSPID']);

if (!defined('_OGONE_TEST'))
	define('_OGONE_TEST',
		(isset($GLOBALS['config_bank_paiement']['config_ogone']['mode_test'])
			AND $GLOBALS['config_bank_paiement']['config_ogone']['mode_test'])?true:false);

// Url sur laquelle envoyer les demandes de paiement
if (!defined('_OGONE_URL'))
	define('_OGONE_URL',_OGONE_TEST?
		// Tests
		"https://secure.ogone.com/ncol/test/orderstandard.asp"
	:
		// Production
		"https://secure.ogone.com/ncol/prod/orderstandard.asp"
	);

// Cle de signature des demandes envoyees a Ogone. A renseigner aussi dans la
// configuration chez Ogone
// Dans Parametres generaux de securite/Méthode de hachage , il faut choisir :
// Composez la séquence à hacher en concatenant la valeur de: Chaque paramètre suivi par la clé.
// Algorithme de hachage : SHA-1
// Pour le charset, utiliser celui du site.
if (!defined('_OGONE_CLE_SHA_IN'))
	define('_OGONE_CLE_SHA_IN',$GLOBALS['config_bank_paiement']['config_ogone']['CLE_SHA_IN']);

// Cle de signature des retour depuis Ogone. A renseigner aussi dans la
// configuration chez Ogone
if (!defined('_OGONE_CLE_SHA_OUT'))
	define('_OGONE_CLE_SHA_OUT',$GLOBALS['config_bank_paiement']['config_ogone']['CLE_SHA_OUT']);


/* ------------------------------------------------------------------------- */

?>