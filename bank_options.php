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

// verifier qu'on sait poser un cookie
if (!isset($_COOKIE['accept_cookie'])) {
	include_spip('inc/cookie');
	spip_setcookie('accept_cookie',$_COOKIE['accept_cookie']=1);
}

if (isset($GLOBALS['meta']['bank_paiement'])
  AND $prestas = unserialize($GLOBALS['meta']['bank_paiement'])
	AND count($prestas = $prestas['presta'])) {
	// initialiser la config de chaque presta actif
	foreach($prestas as $p=>$actif){
		// TODO ajouter une secu !preg_match(',[\W],',$p) ?
		if ($actif) {
			#_chemin(_DIR_PLUGIN_BANK."presta/$p"); // pour les pages de retour
			include_spip("presta/$p/config"); // pour la config par defaut
		}
	}
}


?>