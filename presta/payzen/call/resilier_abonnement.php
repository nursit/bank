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

include_spip('presta/payzen/inc/payzen');

/**
 * Jamais appele directement dans le plugin bank/
 * mais par une eventuelle methode abos/resilier d'un plugin externe
 *
 * @param string $uid
 * @param array|string $config
 * @return bool
 */
function presta_payzen_call_resilier_abonnement_dist($uid, $config = 'payzen'){

	include_spip('presta/payzen/lib/ws-v5/classes');

	include_spip('presta/systempay/inc/systempay');
	include_spip('inc/bank');

	$trans = sql_fetsel("mode,pay_id", "spip_transactions", "abo_uid=" . sql_quote($uid) . " AND mode LIKE " . sql_quote($config . '%'),'','id_transaction','0,1');

	if (!is_array($config)){
		$config = bank_config($trans['mode']);
	}
	$mode = $config['presta'];

	$vads = new PayzenWSv5($config);

	$response = new cancelSubscriptionResponse();
	try {
		$response = $vads->cancelSubscription($trans['pay_id'], $uid);
	}
	catch (Exception $e) {
		spip_log($s="call_resilier_abonnement : erreur ".$e->getMessage(),$mode._LOG_ERREUR);
		return false;
	}

	if ($e = $response->cancelSubscriptionResult->commonResponse->responseCode){
		spip_log($s="call_resilier_abonnement $uid : erreur $e : ".$response->cancelSubscriptionResult->commonResponse->responseCodeDetail,$mode._LOG_ERREUR);
		// 33 : Invalid Subscription => on est donc bien desabonne
		if ($e==33) {
			return true;
		}
		else {
			return false;
		}
	}

	return true;
}