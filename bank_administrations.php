<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('inc/meta');


/**
 * Lister les install de presta
 * @return array
 */
function bank_lister_instal_prestas(){
	$liste_prestas = array();
	$recurs = array();
	$maxfiles = 10000;
	$dir = 'presta/';

	// Parcourir le chemin
	foreach (creer_chemin() as $d){
		$f = $d . $dir;
		if (@is_dir($f)){
			$liste = preg_files($f, "/install[.]php$", $maxfiles-count($liste_prestas), $recurs);
			foreach ($liste as $chemin){
				$liste_prestas[] = dirname(substr($chemin, strlen($f)));
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
	if (!isset($GLOBALS['meta']['bank_paiement'])
	  or !$config = unserialize($GLOBALS['meta']['bank_paiement'])) {
		$config = array();
	}
	foreach ($prestas as $p){
		if (
			(isset($config['presta'][$p]) AND $config['presta'][$p])
			OR (isset($config['presta']["abo_$p"]) AND $config['presta']["abo_$p"])){
			if ($install = charger_fonction("install", "presta/$p", true)){
				$install();
			}
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
function bank_upgrade($nom_meta_base_version, $version_cible){

	$maj = array();
	$maj['create'] = array(
		array('maj_tables', array('spip_transactions', 'spip_bank_recurrences')),
	);

	$maj['0.1.3'] = array(
		array('maj_tables', array('spip_transactions')),
	);
	$maj['0.1.5'] = array(
		array("sql_alter", "table spip_transactions change url_retour url_retour text DEFAULT '' NOT NULL"),
	);

	$maj['1.0.0'] = array(
		array("sql_alter", "table spip_transactions change transaction_hash transaction_hash bigint(21) NOT NULL DEFAULT 0"),
	);
	$maj['1.0.1'] = array(
		array("sql_alter", "table spip_transactions change finie finie tinyint(1) NOT NULL DEFAULT 0"),
		array("sql_alter", "table spip_transactions change tracking_id tracking_id bigint(21) NOT NULL DEFAULT 0"),
		array("sql_alter", "table spip_transactions change id_panier id_panier bigint(21) NOT NULL DEFAULT 0"),
		array("sql_alter", "table spip_transactions change id_facture id_facture bigint(21) NOT NULL DEFAULT 0"),
	);

	$maj['1.1.0'] = array(
		array("sql_alter", "table spip_transactions ADD contenu TEXT NOT NULL DEFAULT ''"),
	);

	$maj['1.2.0'] = array();

	$maj['1.3.0'] = array(
		array("sql_alter", "table spip_transactions ADD refcb varchar(100) NOT NULL DEFAULT ''"),
	);

	$maj['1.4.0'] = array(
		array("sql_alter", "table spip_transactions ADD abo_uid varchar(55) NOT NULL DEFAULT ''"),
		array("sql_alter", "table spip_transactions ADD validite varchar(10) NOT NULL DEFAULT ''"),
	);
	$maj['1.4.1'] = array(
		array("sql_alter", "table spip_transactions ADD id_commande bigint(21) NOT NULL DEFAULT 0"),
	);

	$maj['1.5.0'] = array(
		array("sql_alter", "table spip_transactions ADD erreur tinytext NOT NULL DEFAULT ''"),
	);

	$maj['1.6.1'] = array(
		array("bank_upgrade_config"),
	);
	$maj['1.6.3'] = array(
		array("sql_alter", "table spip_transactions ADD pay_id varchar(100) NOT NULL DEFAULT ''"),
		array("sql_update", "spip_transactions", array('pay_id' => 'refcb', 'refcb' => "''"), "mode=" . sql_quote('paybox')),
	);
	$maj['1.6.4'] = array(
		array("sql_updateq", "spip_transactions", array('statut' => 'attente'), "statut=" . sql_quote('commande') . " AND mode<>'' AND autorisation_id<>''"),
	);
	$maj['1.6.5'] = array(
		array("sql_alter", "table spip_transactions CHANGE autorisation_id autorisation_id varchar(255) NOT NULL DEFAULT ''"),
	);

	// Ajout du champ "devise"
	$maj['2.0.0'] = array(
		array("sql_alter", "table spip_transactions ADD devise char(3) NOT NULL DEFAULT 'EUR' AFTER montant"),
	);

	$maj['2.0.1'] = array(
		array("sql_alter", "TABLE spip_transactions CHANGE auteur_id auteur_id varchar(255) NOT NULL DEFAULT ''"),
		array("sql_alter", "TABLE spip_transactions CHANGE auteur auteur varchar(255) NOT NULL DEFAULT ''"),
	);

	$maj['2.0.2'] = array(
		array("sql_alter", "TABLE spip_transactions CHANGE abo_uid abo_uid varchar(100) NOT NULL DEFAULT ''"),
	);

	// creation de la table spip_bank_recurrences
	$maj['2.1.4'] = array(
		array('maj_tables', array('spip_bank_recurrences')),
	);

	$maj['2.1.5'] = array(
		array("sql_alter", "TABLE spip_transactions CHANGE token token text DEFAULT '' NOT NULL"),
	);

	$maj['2.2.0'] = array(
		array("sql_alter", "TABLE spip_transactions ADD data mediumtext DEFAULT '' NOT NULL AFTER token"),
	);

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);

	bank_presta_install();
}

function bank_upgrade_config(){
	include_spip('inc/config');

	// suppression d'une vieille config Paybox
	if (lire_config("bank_paiement/config_paybox/pubkey", '')){
		ecrire_config("bank_paiement/config_paybox/pubkey", null);
	}
	if (lire_config("bank_paiement/config_abo_paybox/pubkey", '')){
		ecrire_config("bank_paiement/config_abo_paybox/pubkey", null);
	}

	// renommage CybperPlus en SystemPay
	$prestas = lire_config("bank_paiement/presta");
	if (isset($prestas['cyberplus'])){
		$prestas['systempay'] = $prestas['cyberplus'];
		unset($prestas['cyberplus']);
		ecrire_config("bank_paiement/presta", $prestas);
	}
	if (!is_null($c = lire_config("bank_paiement/config_cyberplus"))){
		effacer_config("bank_paiement/config_cyberplus");
		if (!lire_config("bank_paiement/config_systempay", '')){
			ecrire_config("bank_paiement/config_systempay", $c);
		}
	}
	if ($actifs = lire_config("bank_paiement/presta")){
		foreach ($actifs as $mode => $actif){
			// regarder si la config est vide ou non
			$cfg = lire_config("bank_paiement/config_$mode");
			$empty = true;
			foreach ($cfg as $k => $v){
				if (!in_array($k, array('actif', 'presta', 'type', 'mode_test', 'service'))){
					if (is_array($v) ? count($v) : strlen($v)
						AND $v!=="your_email_username_for_paypal@example.org"){
						$empty = false;
					}
				}
			}
			if (!$empty){
				// cles de test
				if (isset($cfg['mode_test']) AND $cfg['mode_test']){
					if ($mode=='paybox' AND isset($cfg['PBX_HMAC_KEY']) AND $cfg['PBX_HMAC_KEY']){
						if (!isset($cfg['PBX_HMAC_KEY_test']) OR !$cfg['PBX_HMAC_KEY_test']){
							ecrire_config("bank_paiement/config_$mode/PBX_HMAC_KEY_test", $cfg['PBX_HMAC_KEY']);
						}
					} elseif ($mode=='systempay' AND isset($cfg['CLE']) AND $cfg['CLE']) {
						if (!isset($cfg['CLE_test']) OR !$cfg['CLE_test']){
							ecrire_config("bank_paiement/config_$mode/CLE_test", $cfg['CLE']);
						}
					}
				}
				ecrire_config("bank_paiement/config_$mode/actif", $actif);
				ecrire_config("bank_paiement/config_$mode/presta", $mode);
				ecrire_config("bank_paiement/config_$mode/type", "acte");
			} else {
				effacer_config("bank_paiement/config_$mode");
			}
		}
		effacer_config("bank_paiement/presta");
	}

	if ($actifs_abo = lire_config("bank_paiement/presta_abo")){
		foreach ($actifs_abo as $mode => $actif){
			// regarder si la config est vide ou non
			$cfg = lire_config("bank_paiement/config_abo_$mode");
			$empty = true;
			foreach ($cfg as $k => $v){
				if (!in_array($k, array('actif', 'presta', 'type', 'mode_test', 'service'))){
					if (is_array($v) ? count($v) : strlen($v)){
						$empty = false;
					}
				}
			}
			if (!$empty){
				// cles de test
				if (isset($cfg['mode_test']) AND $cfg['mode_test']){
					if ($mode=='paybox' AND isset($cfg['PBX_HMAC_KEY']) AND $cfg['PBX_HMAC_KEY']){
						if (!isset($cfg['PBX_HMAC_KEY_test']) OR !$cfg['PBX_HMAC_KEY_test']){
							ecrire_config("bank_paiement/config_$mode/PBX_HMAC_KEY_test", $cfg['PBX_HMAC_KEY']);
						}
					}
				}
				ecrire_config("bank_paiement/config_abo_$mode/actif", $actif);
				ecrire_config("bank_paiement/config_abo_$mode/presta", $mode);
				ecrire_config("bank_paiement/config_abo_$mode/type", "abo");
			} else {
				effacer_config("bank_paiement/config_abo_$mode");
			}
		}
		effacer_config("bank_paiement/presta_abo");
	}
}

/**
 * Desinstallation
 *
 * @param string $nom_meta_base_version
 */
function bank_vider_tables($nom_meta_base_version){
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
function bank_install($action, $prefix, $version_cible){
	$nom_meta_base_version = $prefix . "_base_version";
	switch ($action) {
		case 'test':
			$ok = (isset($GLOBALS['meta'][$nom_meta_base_version])
				AND spip_version_compare($GLOBALS['meta'][$nom_meta_base_version], $version_cible, ">="));
			if ($ok){
				// verifier/maj des fichiers de config
				bank_presta_install();
			}
			return $ok;
			break;
		case 'install':
			bank_upgrade($nom_meta_base_version, $version_cible);
			break;
		case 'uninstall':
			bank_vider_tables($nom_meta_base_version);
			break;
	}
}
