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

/* CyberPlus  ----------------------------------------------------------- */


/**
 * Constantes pour CyberPlus
 * 
 * Vous pouvez définir ces constantes dans votre fichier mes_options.php
 *
 * Il vous faudra obtenir 3 informations de CyberPlus et les définir dans
 * les constantes correspondantes :
 * - Identifiant Boutique à récupérer dans le Back office de la solution de paiement (constante _CYBERPLUS_ID),
 * - Certificat à récupérer dans le Back office de la solution de paiement (constante _CYBERPLUS_CLE)
 *
 * Le fonctionnement est le suivant :
 * - le visiteur de votre site clique le bouton de paiement CyberPlus, il est redirigé sur la banque
 * - là, il peut effectuer 3 actions :
 *   1) annuler : il retourne sur votre site (ce qui annule sa transaction)
 *   2) payer correctement : il reste sur le site de la banque,
 *      MAIS celle-ci a dialoguée avec votre site qui a pris en compte la transaction,
 *      (La transaction passe en statut «ok» et réglée).
 *      Il peut alors retourner sur votre site via le lien de retour OK,
 *      ou quitter la page, peut importe.
 *   3) rater son paiement : il reste sur le site de la banque,
 *      MAIS celle-ci a dialoguée avec votre site qui prend en compte l'erreur
 *      (la transaction passe en statut «echec»). Il peut soit retenter son paiement,
 *      ce qui revient au point 2) ou 3), soit annuler, ce qui revient au point 1)
 *
 */


// CLE : il s'agit d'une cle de sécurité unique de marchand fournit par Cyberplus (appelee certificat)
if (!defined('_CYBERPLUS_CLE'))
	define('_CYBERPLUS_CLE',  $GLOBALS['config_bank_paiement']['config_cyperplus']['CLE']);

// SITE_ID : il s'agit du numéro de SITE_ID, fournit par Cyberplus
if (!defined('_CYBERPLUS_SITE_ID'))
	define('_CYBERPLUS_SITE_ID', $GLOBALS['config_bank_paiement']['config_cyperplus']['SITE_ID']);


// URL d'accès à la banque.
if (!defined('_CYBERPLUS_SERVEUR'))
	define ("_CYBERPLUS_SERVEUR", "https://paiement.systempay.fr/vads-payment/");

// TEST ou PRODUCTION
if (!defined('_CYBERPLUS_MODE'))
	define("_CYBERPLUS_MODE", "PRODUCTION");

# Version du logiciel
if (!defined('_CYBERPLUS_VERSION'))
	define("_CYBERPLUS_VERSION", "V2");

?>
