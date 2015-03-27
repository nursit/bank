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
// code mort ? (mais qui empeche le caching des pages)
#if (!isset($_COOKIE['accept_cookie'])) {
#	include_spip('inc/cookie');
#	spip_setcookie('accept_cookie',$_COOKIE['accept_cookie']=1);
#}

// securite : on initialise une globale le temps de la config des prestas
if (isset($GLOBALS['meta']['bank_paiement'])
  AND $GLOBALS['config_bank_paiement'] = unserialize($GLOBALS['meta']['bank_paiement'])){

	$prestas = (is_array($GLOBALS['config_bank_paiement']['presta'])?$GLOBALS['config_bank_paiement']['presta']:array());
	$prestas = array_filter($prestas);
	if (is_array($GLOBALS['config_bank_paiement']['presta_abo']))
		$prestas = array_merge($prestas,array_filter($GLOBALS['config_bank_paiement']['presta_abo']));
	// initialiser la config de chaque presta actif
	if (count($prestas))
		foreach($prestas as $p=>$actif){
			// TODO ajouter une secu !preg_match(',[\W],',$p) ?
			if ($actif) {
				#_chemin(_DIR_PLUGIN_BANK."presta/$p"); // pour les pages de retour
				include_spip("presta/$p/config"); // pour la config par defaut
			}
		}
	// securite : on ne conserve pas la globale en memoire car elle contient des donnees sensibles
	unset($GLOBALS['config_bank_paiement']);
}

if (!function_exists('affiche_monnaie')) {
function affiche_monnaie($valeur,$decimales=2,$unite=true){
	if ($unite===true){
		$unite = "&nbsp;EUR";
		if (substr(trim($valeur),-1)=="%")
			$unite = "&nbsp;%";
	}
	if (!$unite)
		$unite="";
	return sprintf("%.{$decimales}f",$valeur).$unite;
}
}

