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

/* CMCIC  ----------------------------------------------------------- */


/**
 * Constantes pour CMCIC
 * 
 * Vous pouvez définir ces constantes dans votre fichier mes_options.php
 *
 * Il vous faudra obtenir 3 informations de CMCIC et les définir dans
 * les constantes correspondantes :
 * - le n° de TPE (constante CMCIC_TPE),
 * - le code de société (constante CMCIC_CODESOCIETE),
 * - la clé HMAC-SHA1 (constante CMCIC_CLE)
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


// CLE : il s'agit d'une cle de sécurité unique de marchand fournit par CMCIC
// C'est une clé qui est à demander à CMCIC. Ils transmettront un mail permettant
// de télécharger cette clé sur un site (par exemple https://paiement.creditmutuel.fr/config/download.cgi)
// Une fois le fichier de clé HMAC-SHA1 téléchargé, la clé se trouve sur la première ligne
// de ce fichier après le texte "Version 1 ", en majuscule.
if (!defined('CMCIC_CLE'))
	define('CMCIC_CLE',  $GLOBALS['config_bank_paiement']['config_cmcic']['CLE']); // indiquer sa clé hexa 40 caractères

// TPE : il s'agit du numéro de TPE, fournit par CMCIC pour le marchand
if (!defined('CMCIC_TPE'))
	define('CMCIC_TPE', $GLOBALS['config_bank_paiement']['config_cmcic']['TPE']); // indiquer son numéro de TPE (7 chiffres ?)

// code de société du marchant, fournit par CMCIC
if (!defined('CMCIC_CODESOCIETE'))
	define ('CMCIC_CODESOCIETE', $GLOBALS['config_bank_paiement']['config_cmcic']['CODESOCIETE']); // indiquer code de société (10 caractères ?)


if (!defined('CMCIC_TEST'))
	define('CMCIC_TEST',
		(isset($GLOBALS['config_bank_paiement']['config_cmcic']['mode_test'])
			AND $GLOBALS['config_bank_paiement']['config_cmcic']['mode_test'])?true:false);

// URL d'accès à la banque.
// Par défaut, l'adresse CIC de paiement normal.
// vous pouvez indiquer, soit la constante CMCIC_SERVEUR directement,
// soit définir CMCIC_TEST à TRUE pour utiliser l'adresse de test de CMCIC
if (!defined('CMCIC_SERVEUR')) {
	switch($GLOBALS['config_bank_paiement']['config_cmcic']['service']){
		case "CMUT":
			define ("CMCIC_SERVEUR",
				CMCIC_TEST?
					"https://paiement.creditmutuel.fr/test/paiement.cgi"
					:
					"https://paiement.creditmutuel.fr/paiement.cgi"
			);
			break;
		case "OBC":
			define ("CMCIC_SERVEUR",
				CMCIC_TEST?
					"https://ssl.paiement.banque-obc.fr/test/paiement.cgi"
					:
					"https://ssl.paiement.banque-obc.fr/paiement.cgi"
			);
			break;
		case "CIC":
		default:
			define ("CMCIC_SERVEUR",
				CMCIC_TEST?
					"https://ssl.paiement.cic-banques.fr/test/paiement.cgi"
					:
					"https://ssl.paiement.cic-banques.fr/paiement.cgi"
			);
			break;
	}
}

# Version du logiciel
if (!defined('CMCIC_VERSION'))
	define("CMCIC_VERSION", "3.0");

?>
