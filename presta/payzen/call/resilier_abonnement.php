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

	include_spip('presta/payzen/inc/payzen');
	include_spip('inc/bank');

	$trans = sql_fetsel("mode,pay_id", "spip_transactions", "abo_uid=" . sql_quote($uid) . " AND mode LIKE " . sql_quote($config . '%'), '', 'id_transaction', '0,1');

	if (!is_array($config)){
		$config = bank_config($trans['mode']);
	}
	$mode = $config['presta'];

	$args = [
		'paymentMethodToken' => $trans['pay_id'],
		'subscriptionId' => $uid
	];

	// si on a bien configurer le password API GET
	if (payzen_api_password($config)) {
		$response = payzen_api_call($config, "Subscription/Cancel", $args);

		if (!$response or empty($response['status'])) {
			return false;
		}

		// {"webService":"Subscription\/Cancel","version":"V4","applicationVersion":"5.10.3","status":"ERROR","answer":{"errorCode":"INT_030","errorMessage":"invalid payment method token","detailedErrorCode":null,"detailedErrorMessage":"Input value not defined [name=paymentMethodToken]","ticket":"null","shopId":"xxx","_type":"V4\/WebService\/WebServiceError"},"ticket":null,"serverDate":"2020-08-13T11:21:04+00:00","applicationProvider":"PAYZEN","metadata":null,"_type":"V4\/WebService\/Response"}
		if ($response['status'] !== 'SUCCESS') {
			$errorCode = (empty($response['answer']['errorCode']) ? 99 : $response['answer']['errorCode']);
			$errorMessage = (empty($response['answer']['errorMessage']) ? '' : $response['answer']['errorMessage']);

			switch ($errorCode) {
				case 'PSP_033': // Invalid Subscription => on est donc bien desabonne
					return true;
				case 'INT_030': // Invalide payment method token
				case 'PSP_032': // Subscription not found
				// 99 	Erreur indéfinie
				default:
					spip_log("call_resilier_abonnement $uid (REST) : erreur $errorCode $errorMessage", $mode . _LOG_ERREUR);
					return false;
			}
		}

		// ok on a reusi donc
		spip_log("call_resilier_abonnement $uid (REST) : SUCCESS", $mode);
		return true;
	}

	// @deprecated
	// sinon utiliser l'API SOAP tant qu'elle marche encore (->janvier 2023)
	// permet une transition en douceur lors de la mise a jour du plugin
	else {

		include_spip('presta/payzen/lib/ws-v5/classes');
		$vads = new PayzenWSv5($config);
		$response = new cancelSubscriptionResponse();
		try {
			$response = $vads->cancelSubscription($args['paymentMethodToken'], $args['subscriptionId']);
		} catch (Exception $e) {
			spip_log($s = "call_resilier_abonnement $uid (SOAP) : erreur " . $e->getMessage(), $mode . _LOG_ERREUR);
		}

		if ($e = $response->cancelSubscriptionResult->commonResponse->responseCode){
			spip_log($s = "call_resilier_abonnement $uid : erreur $e : " . $response->cancelSubscriptionResult->commonResponse->responseCodeDetail, $mode . _LOG_ERREUR);
			// 33 : Invalid Subscription => on est donc bien desabonne
			if ($e==33){
				return true;
			} else {
				return false;
			}
		}

		spip_log("call_resilier_abonnement $uid (SOAP) : SUCCESS", $mode);
		return true;

	}

	return false;

}