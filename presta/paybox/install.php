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

/**
 * Installation des fichiers de configuration/parametrage PAYBOX
 */
function presta_paybox_install_dist(){
	include_spip('inc/config');
	if (file_exists($f = _DIR_ETC . "presta/paybox/pbx_ids.php")){
		include_once($f);
		// la fonction bank_paybox_pbx_ids est definie dans le fichier pbx_ids.php
		if (function_exists("bank_paybox_pbx_ids")){
			$config = bank_paybox_pbx_ids();
			ecrire_config("bank_paiement/config_paybox", $config);
			@unlink($f);
		}
	}
	if (!lire_config("bank_paiement/config_paybox", '')){
		ecrire_config("bank_paiement/config_paybox", array('PBX_IDENTIFIANT' => '2', 'PBX_SITE' => '1999888', 'PBX_RANG' => '99'));
	}
	// effacer cette vieille config
	if (lire_config("bank_paiement/config_paybox/pubkey", '')){
		ecrire_config("bank_paiement/config_paybox/pubkey", null);
	}
	if (lire_config("bank_paiement/config_abo_paybox/pubkey", '')){
		ecrire_config("bank_paiement/config_abo_paybox/pubkey", null);
	}
}
