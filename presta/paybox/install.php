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

/**
 * Installation des fichiers de configuration/parametrage PAYBOX
 */
function presta_paybox_install_dist(){
	if (file_exists($f=_DIR_ETC."presta/paybox/pbx_ids.php")){
		include_once($f);
		if (function_exists("bank_paybox_pbx_ids")){
			$config = bank_paybox_pbx_ids();
			include_spip('inc/config');
			ecrire_config("bank_paiement/config_paybox",$config);
			@unlink($f);
		}
	}
	else {
		include_spip('inc/config');
		if (!lire_config("bank_paiement/config_paybox",'')){
			ecrire_config("bank_paiement/config_paybox",array('PBX_IDENTIFIANT'=>'2','PBX_SITE'=>'1999888','PBX_RANG'=>'99'));
		}
	}
}

?>