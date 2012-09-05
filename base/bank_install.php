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
	$current_version = 0.0;
	if (   (!isset($GLOBALS['meta'][$nom_meta_base_version]) )
			|| (($current_version = $GLOBALS['meta'][$nom_meta_base_version])!=$version_cible)){
		include_spip('base/abstract_sql');
		if (spip_version_compare($current_version,"0.0.0","<=")){
			include_spip('base/create');
			include_spip('base/serial');
			creer_base();
			echo "Paiement Install<br/>";
			ecrire_meta($nom_meta_base_version,$current_version=$version_cible,'non');
		}
		if (spip_version_compare($current_version,"0.1.3","<=")){
			include_spip('base/create');
			include_spip('base/serial');
			maj_tables(array('spip_transactions'));
			ecrire_meta($nom_meta_base_version,$current_version="0.1.3",'non');
		}
		if (spip_version_compare($current_version,"0.1.5","<=")){
			include_spip('base/create');
			include_spip('base/serial');
			sql_alter("table spip_transactions change url_retour url_retour text DEFAULT '' NOT NULL");
			ecrire_meta($nom_meta_base_version,$current_version="0.1.5",'non');
		}
		if (spip_version_compare($current_version,"1.0.0","<=")){
			include_spip('base/create');
			include_spip('base/serial');
			sql_alter("table spip_transactions change transaction_hash transaction_hash bigint(21) NOT NULL DEFAULT 0");
			ecrire_meta($nom_meta_base_version,$current_version="1.0.0",'non');
		}
		if (spip_version_compare($current_version,"1.0.1","<=")){
			include_spip('base/create');
			include_spip('base/serial');
			sql_alter("table spip_transactions change finie finie tinyint(1) NOT NULL DEFAULT 0");
			sql_alter("table spip_transactions change tracking_id tracking_id bigint(21) NOT NULL DEFAULT 0");
			sql_alter("table spip_transactions change id_panier id_panier bigint(21) NOT NULL DEFAULT 0");
			sql_alter("table spip_transactions change id_facture id_facture bigint(21) NOT NULL DEFAULT 0");
			ecrire_meta($nom_meta_base_version,$current_version="1.0.1",'non');
		}
		if (spip_version_compare($current_version,"1.1.0","<=")){
			include_spip('base/create');
			include_spip('base/serial');
			sql_alter("table spip_transactions ADD contenu TEXT NOT NULL DEFAULT ''");
			ecrire_meta($nom_meta_base_version,$current_version="1.1.0",'non');
		}

		bank_presta_install();
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