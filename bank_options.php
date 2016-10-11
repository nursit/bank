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


// securite : on initialise une globale le temps de la config des prestas
if (isset($GLOBALS['meta']['bank_paiement'])
  AND $GLOBALS['config_bank_paiement'] = unserialize($GLOBALS['meta']['bank_paiement'])){

	foreach($GLOBALS['config_bank_paiement'] as $nom=>$config){
		if (strncmp($nom,"config_",7)==0
			AND isset($config['actif'])
		  AND $config['actif']
			AND isset($config['presta'])
		  AND $presta = $config['presta']){
			// inclure le fichier config du presta correspondant
			include_spip("presta/$presta/config");
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
 * @param array|string $config
 *   string dans le cas "gratuit" => on va chercher la config via bank_config()
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

	// $config de type string ?
	include_spip('inc/bank');
	if (is_string($config)){
		$config = bank_config($config,$type=='abo');
	}

	$quoi = ($type=='abo'?'abonnement':'acte');

	if ($payer = charger_fonction($quoi,'presta/'.$config['presta'].'/payer',true)){
		return $payer($config, $id_transaction, $transaction_hash, $options);
	}

	spip_log("Pas de payer/$quoi pour presta=".$config['presta'],"bank"._LOG_ERREUR);
	return "";

}

/**
 * Afficher le bouton pour gerer/interrompre un abonnement
 * @param array|string $config
 * @param string $abo_uid
 * @return array|string
 */
function bank_affiche_gerer_abonnement($config,$abo_uid){
	// $config de type string ?
	include_spip('inc/bank');
	if (is_string($config)){
		$config = bank_config($config,true);
	}

	if ($trans = sql_fetsel("*","spip_transactions",$w="abo_uid=".sql_quote($abo_uid).' AND mode LIKE '.sql_quote($config['presta'].'%')." AND ".sql_in('statut',array('ok','attente')),'','id_transaction')){
		$config = bank_config($trans['mode']);
		$fond = "modeles/gerer_abonnement";
		if (trouver_fond($f="presta/".$config['presta']."/payer/gerer_abonnement")){
			$fond = $f;
		}
		return recuperer_fond($fond,array('presta'=>$config['presta'],'id_transaction'=>$trans['id_transaction'],'abo_uid'=>$abo_uid));
	}

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

/**
 * Annoncer SPIP + plugin&version pour les logs de certains providers
 * @param string $format
 * @return string
 */
function bank_annonce_version_plugin($format = 'string'){
	$infos = array(
		'name' => 'SPIP '.$GLOBALS['spip_version_branche'].' + Bank',
		'url' => 'https://github.com/nursit/bank',
		'version' => '',
	);
	include_spip('inc/filtres');
	if ($info_plugin = chercher_filtre("info_plugin")){
		$infos['version'] = 'v' . $info_plugin("bank","version");
	}

	if ($format==='string'){
		return $infos['name'] . $infos['version'] . '(' . $infos['url'] . ')';
	}

	return $infos;
}