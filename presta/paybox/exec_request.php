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


function presta_paybox_exec_request_dist($params,$request='modulev2.cgi'){
	if (defined('_DIR_PAYBOX_EXEC'))
		$path_bin = _DIR_PAYBOX_EXEC;
	else
		$path_bin = _DIR_PLUGIN_BANK . "presta/paybox/bin/";
	$path_bin .= $request;
	if (!file_exists($path_bin)){
		spip_log("Binaire $path_bin non trouve","paybox."._LOG_ERREUR);
		return false;
	}
	$path_bin = realpath($path_bin);

	// signaler le passage en ligne de commande
	$params['PBX_MODE'] = '4';

	// transformer la table d'arguments en commande shell
	include_spip("presta/paybox/inc/paybox");
	$params = paybox_shell_args($params);

	//	Appel du binaire request
	spip_log("paybox_exec_request : $path_bin $params",'paybox');
	$result=shell_exec("$path_bin $params");

	return $result;
}
