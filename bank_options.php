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

function autoriser_utilisermodepaiementabo_dist($faire, $mode='', $id=0, $qui = NULL, $opt = NULL){
	include_spip("presta/$mode/config");
	$fonctions = array('autoriser_'.$mode.'_'.$faire,'autoriser_'.$mode.'_'.$faire.'_dist','autoriser_'.$mode,'autoriser_'.$mode.'_dist');
	foreach ($fonctions as $f) {
		if (function_exists($f)) {
			return $f($faire,$mode,$id,$qui,$opt);
		}
	}
	return true;
}

/**
 * Seuls les webmestres peuvent encaisser un cheque
 * webmaster.
 * @param $faire
 * @param $type
 * @param $id_transaction
 * @param $qui
 * @param $opt
 * @return bool
 */
function autoriser_transaction_encaissercheque_dist($faire, $type, $id_transaction, $qui, $opt) { 
	return autoriser('webmestre');
}

/**
 * Seuls les webmestres peuvent rembourser une transaction
 * webmaster.
 * @param $faire
 * @param $type
 * @param $id_transaction
 * @param $qui
 * @param $opt
 * @return bool
 */
function autoriser_transaction_rembourser_dist($faire, $type, $id_transaction, $qui, $opt) {
	return autoriser('webmestre');
}

/**
 * Transformer un tableau d'argument en liste arg=value pour le shell
 * (en echappant de maniere securisee)
 * @param $params
 * @return string
 */
function bank_shell_args($params){
	$res = "";
	foreach($params as $k=>$v){
		$res .= " ".escapeshellcmd($k)."=".escapeshellcmd($v);
	}
	return $res;
}

/**
 * @param array $transaction
 * @return string
 */
function bank_email_porteur($transaction){
	$mail = '';

	// recuperer l'email
	if (!$transaction['id_auteur']
		OR !$mail = sql_getfetsel('email','spip_auteurs','id_auteur='.intval($transaction['id_auteur']))){

		if (strpos($transaction['auteur'],"@")!==false
			AND include_spip('inc/filtres')
		  AND email_valide($transaction['auteur'])){
			$mail = $transaction['auteur'];
		}
	}

	// fallback : utiliser l'email du webmetre du site pour permettre le paiement coute que coute
	if (!$mail)
		$mail = $GLOBALS['meta']['email_webmaster'];

	return $mail;
}