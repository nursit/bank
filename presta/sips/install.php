<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2018 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Installation des fichiers de configuration/parametrage SIPS
 */
function presta_sips_install_dist(){
	include_spip('inc/config');
	if (!lire_config("bank_paiement/config_sips",'')){
		$merchant_id = "";
		$certif = "";
		if (isset($GLOBALS['meta']['bank_sips_merchant_id'])
		  AND file_exists(_DIR_ETC . $GLOBALS['meta']['bank_sips_merchant_id'])){
			include_once(_DIR_ETC . $GLOBALS['meta']['bank_sips_merchant_id']);
			$boutique = "";
			if (function_exists($f = "bank_sips".$boutique."_merchant_id"))
				$merchant_id = $f();
			if (file_exists($f=_DIR_ETC .dirname($GLOBALS['meta']['bank_sips_merchant_id'])."/certif.fr.$merchant_id")){
				lire_fichier($f,$certif);
			}
		}
		if (defined('_SIPS_PRESTA') AND $merchant_id AND $certif){
			$presta = _SIPS_PRESTA;
		}
		else {
			$presta = "mercanet";
			$merchant_id = '082584341411111';
			lire_fichier(_DIR_PLUGIN_BANK."presta/sips/bin/$presta/param/certif.fr.$merchant_id",$certif);
		}

		ecrire_config("bank_paiement/config_sips",
			array(
				'merchant_id'=>'2',
				'service'=>$presta,
				'certificat'=>$certif,
			)
		);
	}
}
