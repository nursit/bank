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
  AND $GLOBALS['config_bank_paiement'] = unserialize($GLOBALS['meta']['bank_paiement'])
	AND count($prestas = $GLOBALS['config_bank_paiement']['presta'])) {
	// initialiser la config de chaque presta actif
	foreach($prestas as $p=>$actif){
		// TODO ajouter une secu !preg_match(',[\W],',$p) ?
		if ($actif) {
			#_chemin(_DIR_PLUGIN_BANK."presta/$p"); // pour les pages de retour
			include_spip("presta/$p/config"); // pour la config par defaut
		}
	}
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
		$unite=="";
	return sprintf("%.{$decimales}f",$valeur).$unite;
}
}

function autoriser_bank_configurer($faire, $mode='', $id=0, $qui = NULL, $opt = NULL){
	return autoriser('webmestre');
}

function autoriser_utilisermodepaiement_dist($faire, $mode='', $id=0, $qui = NULL, $opt = NULL){
	include_spip("presta/$mode/config");
	$fonctions = array('autoriser_'.$mode.'_'.$faire,'autoriser_'.$mode.'_'.$faire.'_dist','autoriser_'.$mode,'autoriser_'.$mode.'_dist');
	foreach ($fonctions as $f) {
		if (function_exists($f)) {
			return $f($faire,$mode,$id,$qui,$opt);
		}
	}
	return true;
}

/* Par défaut, on interdit la personne qui a payé d'encaisser son
 * propre chèque, même si la personne en question dispose des droits
 * webmaster. */
function autoriser_transaction_encaissercheque_dist($faire, $type, $id_transaction, $qui, $opt) { 
	if(autoriser('webmestre')) {
		include_spip('base/abstract_sql');

		$id_auteur = sql_getfetsel("id_auteur", "spip_transactions", "id_transaction=" . intval($id_transaction));
		if($id_auteur != $qui['id_auteur']) {
			return true;
		}
	}

	return false;
}

?>
