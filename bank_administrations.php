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

include_spip('inc/meta');


/**
 * Lister les install de presta
 * @return array
 */
function bank_lister_instal_prestas(){
	$liste_prestas=array();
	$recurs = array();
	$maxfiles = 10000;
	$dir = 'presta/';

	// Parcourir le chemin
	foreach (creer_chemin() as $d) {
		$f = $d.$dir;
		if (@is_dir($f)){
			$liste = preg_files($f,"/install[.]php$",$maxfiles-count($liste_prestas),$recurs);
			foreach($liste as $chemin){
				$liste_prestas[] = dirname(substr($chemin,strlen($f)));
			}
		}
	}
	return $liste_prestas;
}

/**
 * Proceder a l'installation pour chaque presta bancaire
 */
function bank_presta_install(){
	$prestas = bank_lister_instal_prestas();
	#var_dump($prestas);
	$config = unserialize($GLOBALS['meta']['bank_paiement']);
	foreach ($prestas as $p) {
		if (
		 (isset($config['presta'][$p]) AND $config['presta'][$p])
		 OR (isset($config['presta']["abo_$p"]) AND $config['presta']["abo_$p"])) {
			if ($install = charger_fonction("install", "presta/$p",true))
				$install();
			#var_dump($install);
		}
	}
}

/**
 * Upgrade de la base
 * 
 * @param string $nom_meta_base_version
 * @param string $version_cible
 */
function bank_upgrade($nom_meta_base_version,$version_cible){

	$maj = array();
	$maj['create'] = array(
		array('maj_tables',array('spip_transactions')),
	);

	$maj['0.1.3'] = array(
		array('maj_tables',array('spip_transactions')),
	);
	$maj['0.1.5'] = array(
		array("sql_alter","table spip_transactions change url_retour url_retour text DEFAULT '' NOT NULL"),
	);

	$maj['1.0.0'] = array(
		array("sql_alter","table spip_transactions change transaction_hash transaction_hash bigint(21) NOT NULL DEFAULT 0"),
	);
	$maj['1.0.1'] = array(
		array("sql_alter","table spip_transactions change finie finie tinyint(1) NOT NULL DEFAULT 0"),
		array("sql_alter","table spip_transactions change tracking_id tracking_id bigint(21) NOT NULL DEFAULT 0"),
		array("sql_alter","table spip_transactions change id_panier id_panier bigint(21) NOT NULL DEFAULT 0"),
		array("sql_alter","table spip_transactions change id_facture id_facture bigint(21) NOT NULL DEFAULT 0"),
	);

	$maj['1.1.0'] = array(
		array("sql_alter","table spip_transactions ADD contenu TEXT NOT NULL DEFAULT ''"),
	);

	$maj['1.2.0'] = array(
	);

	$maj['1.3.0'] = array(
		array("sql_alter","table spip_transactions ADD refcb varchar(100) NOT NULL DEFAULT ''"),
	);

	$maj['1.4.0'] = array(
		array("sql_alter","table spip_transactions ADD abo_uid varchar(55) NOT NULL DEFAULT ''"),
		array("sql_alter","table spip_transactions ADD validite varchar(10) NOT NULL DEFAULT ''"),
	);
	$maj['1.4.1'] = array(
		array("sql_alter","table spip_transactions ADD id_commande bigint(21) NOT NULL DEFAULT 0"),
	);

	$maj['1.5.0'] = array(
		array("sql_alter","table spip_transactions ADD erreur tinytext NOT NULL DEFAULT ''"),
	);

	$maj['1.6.1'] = array(
		array("bank_upgrade_config"),
	);

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);

	bank_presta_install();
}

function bank_upgrade_config(){
	// suppression d'une vieille config Paybox
	if (lire_config("bank_paiement/config_paybox/pubkey",'')){
		ecrire_config("bank_paiement/config_paybox/pubkey",null);
	}
	if (lire_config("bank_paiement/config_abo_paybox/pubkey",'')){
		ecrire_config("bank_paiement/config_abo_paybox/pubkey",null);
	}

	// renommage CybperPlus en SystemPay
	$prestas = lire_config("bank_paiement/presta");
	if (isset($prestas['cyberplus'])){
		$prestas['systempay'] = $prestas['cyberplus'];
		unset($prestas['cyberplus']);
		ecrire_config("bank_paiement/presta",$prestas);
	}
	if (!is_null($c = lire_config("bank_paiement/config_cyberplus"))){
		ecrire_config("bank_paiement/config_cyberplus",null);
		if (!lire_config("bank_paiement/config_systempay",'')){
			ecrire_config("bank_paiement/config_systempay",$c);
		}
	}
	if ($actifs = lire_config("bank_paiement/presta")){
		foreach($actifs as $mode=>$actif){
			ecrire_config("bank_paiement/config_$mode/actif",$actif);
			ecrire_config("bank_paiement/config_$mode/presta",$mode);
			ecrire_config("bank_paiement/config_$mode/type","acte");
		}
		effacer_config("bank_paiement/presta");
	}

	if ($actifs_abo = lire_config("bank_paiement/presta_abo")){
		foreach($actifs_abo as $mode=>$actif){
			ecrire_config("bank_paiement/config_abo_$mode/actif",$actif);
			ecrire_config("bank_paiement/config_abo_$mode/presta",$mode);
			ecrire_config("bank_paiement/config_abo_$mode/type","abo");
		}
		effacer_config("bank_paiement/presta_abo");
	}
}

/**
 * Desinstallation
 *
 * @param string $nom_meta_base_version
 */
function bank_vider_tables($nom_meta_base_version) {
	include_spip('base/abstract_sql');
	effacer_meta($nom_meta_base_version);
	sql_drop_table("spip_transactions");
	sql_drop_table("spip_forms_donnees_transactions");
}

/**
 * Test du statut d'install
 *
 * @param string $action
 * @param string $prefix
 * @param string $version_cible
 * @return bool
 */
function bank_install($action,$prefix,$version_cible){
	$version_base = $GLOBALS[$prefix."_base_version"];
	switch ($action){
		case 'test':
			$ok = (isset($GLOBALS['meta'][$prefix."_base_version"])
				AND spip_version_compare($GLOBALS['meta'][$prefix."_base_version"],$version_cible,">="));
			if ($ok){
				// verifier/maj des fichiers de config
				bank_presta_install();
			}
			return $ok;
			break;
		case 'install':
			bank_upgrade($prefix."_base_version",$version_cible);
			break;
		case 'uninstall':
			bank_vider_tables($prefix."_base_version");
			break;
	}
}

?>