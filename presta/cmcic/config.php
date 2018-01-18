<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2018 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

/* CMCIC  ----------------------------------------------------------- */


/**
 * Constantes pour CMCIC
 * 
 * Vous pouvez définir ces constantes dans votre fichier mes_options.php
 *
 * Il vous faudra obtenir 3 informations de CMCIC et les définir dans
 * la configuration du plugin :
 * - le n° de TPE,
 * - le code de société,
 * - la clé HMAC-SHA1
 *
 * Il vous faudra leur indiquer l'adresse suivante comme «URL CGI2»
 * (il faut nécéssairement leur téléphoner pour ça. Il n'y a pas d'interface web le faire) :
 * - http://votresite.tld/?action=bank_autoresponse&bankp=cmcic
 *
 * Le fonctionnement est le suivant :
 * - le visiteur de votre site clique le bouton de paiement CMCIC, il est redirigé sur la banque
 * - là, il peut effectuer 3 actions :
 *   1) annuler : il retourne sur votre site (ce qui annule sa transaction)
 *   2) payer correctement : il reste sur le site de la banque,
 *      MAIS celle-ci a dialoguée avec votre site qui a pris en compte la transaction,
 *      via l'URL CGI2 (La transaction passe en statut «ok» et réglée).
 *      Il peut alors retourner sur votre site via le lien de retour OK,
 *      ou quitter la page, peut importe.
 *   3) rater son paiement : il reste sur le site de la banque,
 *      MAIS celle-ci a dialoguée avec votre site qui prend en compte l'erreur
 *      (la transaction passe en statut «echec»). Il peut soit retenter son paiement,
 *      ce qui revient au point 2) ou 3), soit annuler, ce qui revient au point 1)
 *
 * C'est donc uniquement l'action bank_autoresponse ici qui valide un paiement réussi.
 * Le lien de retour OK ou NOK est simplement un lien de retour cliqué après le paiement,
 * mais n'ont pas d'action sur les statuts de la transaction.
 * Ces liens constateront que le paiement a bien été effectué ou non, et redirigeront sur
 * le squelette SPIP prévu en conséquence.
 */


# Version du logiciel
if (!defined('_CMCIC_VERSION'))
	define("_CMCIC_VERSION", "3.0");

