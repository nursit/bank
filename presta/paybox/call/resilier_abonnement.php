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

include_spip('presta/paybox/inc/paybox');

/**
 * Jamais appele directement dans le plugin bank/
 * mais par une eventuelle methode abos/resilier d'un plugin externe
 *
 * @param string $uid
 * @param array|string $config
 * @return bool
 */
function presta_paybox_call_resilier_abonnement_dist($uid, $config = 'paybox'){

	include_spip('inc/bank');
	if (!is_array($config)){
		$mode = sql_getfetsel("mode", "spip_transactions", "abo_uid=" . sql_quote($uid) . " AND statut=" . sql_quote('ok') . " AND mode LIKE " . sql_quote($config . '%'));
		$config = bank_config($mode);
	}
	$mode = $config['mode'];

	$args =
		"VERSION=001"
		. "&TYPE=001"
		. "&SITE=" . str_pad($config['PBX_SITE'], 7, "0", STR_PAD_LEFT)
		. "&MACH=" . str_pad($config['PBX_RANG'], 3, "0", STR_PAD_LEFT)
		. "&IDENTIFIANT=" . $config['PBX_IDENTIFIANT']
		. "&ABONNEMENT=" . $uid;

	$url = paybox_url_resil($config) . "?" . $args;

	include_spip('inc/distant');
	$res = recuperer_url($url);
	if (!$res or empty($res['page'])) {
		spip_log("paybox_call_resilier_abonnement: Echec appel de recuperer_url sur $url", 'paybox_abos_resil' . _LOG_ERREUR);
		return false;
	}

	parse_str($res['page'], $r);

	if (!empty($r['ACQ']) and $r['ACQ']=='OK'
		and !empty($r['ABONNEMENT']) and $r['ABONNEMENT']==$uid){
		spip_log("paybox_call_resilier_abonnement: uid:$uid, $url, reponse:".json_encode($res), 'paybox_abos_resil' . _LOG_DEBUG);
		return true;
	}

	spip_log("paybox_call_resilier_abonnement: uid:$uid, $url, reponse:".json_encode($res), 'paybox_abos_resil' . _LOG_ERREUR);
	return false;
}
