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

include_spip('presta/paybox/inc/paybox');

// il faut avoir un id_transaction et un transaction_hash coherents
// pour se premunir d'une tentative d'appel exterieur
function presta_paybox_call_resilier_abonnement_dist($uid){

	$parm = paybox_pbx_ids('abo');
	
	$args = 
	  "VERSION=001"
	. "&TYPE=001"
	. "&SITE=" . str_pad($parm['PBX_SITE'],7,"0",STR_PAD_LEFT)
	. "&MACH=" . str_pad($parm['PBX_RANG'],3,"0",STR_PAD_LEFT)
	. "&IDENTIFIANT=" . $parm['PBX_IDENTIFIANT']
	. "&ABONNEMENT=" . $uid;
	
	$url = _PAYBOX_URL_RESIL . "?" . $args;
	
	include_spip('inc/distant');
	$reponse = recuperer_page($url);
	spip_log("uid:$uid, $url, reponse:$reponse",'paybox_abos_resil');
	
	parse_str($reponse,$res);
	if ($res['ACQ']=='OK' AND $res['ABONNEMENT']==$uid)
		return true;
		
	return false;
}
