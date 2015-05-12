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

include_spip('presta/paybox/inc/paybox');

/**
 * Jamais appele directement dans le plugin bank/
 * mais par une eventuelle methode abos/resilier d'un plugin externe
 *
 * TODO : recuperer $config depuis la transaction si pas fourni ?
 *
 * @param string $uid
 * @param array $config
 * @return bool
 */
function presta_paybox_call_resilier_abonnement_dist($uid, $config=null){

	include_spip('inc/bank');
	if (!$config){
		$config = bank_config("paybox",true);
	}

	$args = 
	  "VERSION=001"
	. "&TYPE=001"
	. "&SITE=" . str_pad($config['PBX_SITE'],7,"0",STR_PAD_LEFT)
	. "&MACH=" . str_pad($config['PBX_RANG'],3,"0",STR_PAD_LEFT)
	. "&IDENTIFIANT=" . $config['PBX_IDENTIFIANT']
	. "&ABONNEMENT=" . $uid;
	
	$url = paybox_url_resil($config) . "?" . $args;
	
	include_spip('inc/distant');
	$reponse = recuperer_page($url);
	spip_log("uid:$uid, $url, reponse:$reponse",'paybox_abos_resil');
	
	parse_str($reponse,$res);
	if ($res['ACQ']=='OK' AND $res['ABONNEMENT']==$uid)
		return true;
		
	return false;
}
