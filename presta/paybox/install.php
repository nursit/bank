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
	$dir = sous_repertoire(_DIR_ETC,'presta');
	$dir = sous_repertoire($dir,'paybox');

	if (!file_exists($dir."pbx_ids.php")){
		$merchant_config = "<"."?php
		function bank_paybox_pbx_ids(){return array('PBX_IDENTIFIANT'=>'2','PBX_SITE'=>'1999888','PBX_RANG'=>'99');}\n"
		. "?".">";
		ecrire_fichier($dir."pbx_ids.php",$merchant_config);
		ecrire_meta("bank_paybox_pbx_ids",substr($dir,strlen(_DIR_ETC))."pbx_ids.php");
	}
}


?>