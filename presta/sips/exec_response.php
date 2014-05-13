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


function presta_sips_exec_response_dist($service,$params,$certificat,$dir_logo,$response = 'response'){
	$path_bin = _DIR_PLUGIN_BANK . "sips/bin/$response";
	if (!file_exists($path_bin)){
		spip_log("Binaire $path_bin non trouve","sips."._LOG_ERREUR);
		return false;
	}
	$path_bin = realpath($path_bin);

	include_spip("presta/sips/inc/sips");
	$merchant_id = 0;
	if (isset($params['merchant_id'])){
		$merchant_id = $params['merchant_id'];
		unset($params['merchant_id']);
	}
	$realdir = sips_ecrire_config_merchant($service,$merchant_id,$certificat,$dir_logo);
	$params['pathfile'] = $realdir."/pathfile";

	// transformer la table d'arguments en commande shell
	$params = sips_shell_args($params);

	//	Appel du binaire response
	spip_log("sips_exec_response $response : $path_bin $params",'sips');
	$result=exec("$path_bin $params");
	return $result;
}