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

/* Systempay  ----------------------------------------------------------- */


/**
 * Constantes pour Systempay
 * 
 * Vous pouvez définir ces constantes dans votre fichier mes_options.php
 *
 * Il vous faudra obtenir 3 informations de Systempay et les définir dans
 * les constantes correspondantes :
 * - Identifiant Boutique à récupérer dans le Back office de la solution de paiement (constante _SYSTEMPAY_ID),
 * - Certificat à récupérer dans le Back office de la solution de paiement (constante _SYSTEMPAY_CLE)
 *
 * Le fonctionnement est le suivant :
 * - le visiteur de votre site clique le bouton de paiement Systempay, il est redirigé sur la banque
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

# Version du logiciel
if (!defined('_SYSTEMPAY_VERSION'))
	define("_SYSTEMPAY_VERSION", "V2");
