<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2015 - Distribue sous licence GNU/GPL
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

/**
 * Fonction appelee par la balise #PAYER_ACTE et #PAYER_ABONNEMENT
 * @param array $config
 * @param string $type
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param array|string|null $options
 * @return string
 */
function bank_affiche_payer($config,$type,$id_transaction,$transaction_hash,$options=null){

	// compatibilite ancienne syntaxe, titre en 4e argument de #PAYER_XXX
	if (is_string($options)){
		$options = array(
			'title' => $options,
		);
	}
	// invalide ou null ?
	if (!is_array($options)) {
		$options = array();
	}

	include_spip('inc/bank');
	if (is_string($config)){
		include_spip('inc/bank');
		$config = bank_config($config,$type=='abo');
	}

	$quoi = ($type=='abo'?'abonnement':'acte');

	if ($payer = charger_fonction($quoi,'presta/'.$config['presta'].'/payer',true)){
		return $payer($config, $id_transaction, $transaction_hash, $options);
	}

	spip_log("Pas de payer/$quoi pour presta=".$config['presta'],"bank"._LOG_ERREUR);
	return "";

}


function bank_trouver_logo($mode,$logo){
	// d'abord dans un dossier presta/
	if ($f=find_in_path("presta/$mode/logo/$logo"))
		return $f;
	// sinon le dossier generique
	elseif ($f=find_in_path("bank/logo/$logo"))
		return $f;
	return "";
}