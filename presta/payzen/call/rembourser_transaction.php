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
 * mais par des éventuelles fonctions internes
 *
 * @param int $id_transaction
 * @param array|string $config
 * @return bool
 */
function presta_payzen_call_rembourser_transaction_dist($id_transaction, $config = 'payzen'){

	include_spip('presta/payzen/inc/payzen');
	include_spip('inc/bank');

	$trans = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction) . " AND mode LIKE " . sql_quote($config . '%'), '', 'id_transaction', '0,1');
	if (empty($trans)) {
		spip_log("call_rembourser_transaction #$id_transaction : transaction inexistante ou n'utilisant pas ce prestataire", ($mode ?? $config) . _LOG_ERREUR);
		return false;
	}

	if (!is_array($config)){
		$config = bank_config($trans['mode']);
	}
	$mode = $config['presta'];

	$data = json_decode($trans['data'], true) ?? [];
	$uuid = $data['uuid'] ?? '';

	if (!$uuid) {
		spip_log("call_rembourser_transaction #$id_transaction : transaction sans uuid en base, on ne peut rien faire", $mode . _LOG_ERREUR);
		return false;
	}

	$args = [
		'uuid' => $uuid,
		'resolutionMode' => 'AUTO', // annulation de la transaction si pas encore remisée, remboursement sinon
	];

	// si on a bien configurer le password API GET
	if (payzen_api_password($config)) {
		$response = payzen_api_call($config, "Transaction/CancelOrRefund", $args);
		//spip_log("call_rembourser_transaction #$id_transaction $uuid (REST) : " . json_encode($response), $mode . _LOG_DEBUG);

		if (!$response or empty($response['status'])) {
			return false;
		}

		if (!$response || empty($response['status'])) {
			return false;
		}

		if ($response['status'] !== 'SUCCESS') {
			$errorCode = (empty($response['answer']['errorCode']) ? 99 : $response['answer']['errorCode']);
			$errorMessage = (empty($response['answer']['errorMessage']) ? '' : $response['answer']['errorMessage']);

			switch ($errorCode) {
				default:
					spip_log("call_rembourser_transaction #$id_transaction $uuid (REST) : erreur $errorCode $errorMessage", $mode . _LOG_ERREUR);
					return false;
			}
		}

		if (empty($response['answer'])) {
			spip_log("call_rembourser_transaction #$id_transaction $uuid (REST) : Réponse sans 'answer'", $mode . _LOG_ERREUR);
			return false;
		}

		$transaction_info = $response['answer'];

		// ok on a reusi donc
		spip_log("call_rembourser_transaction $uuid (REST) : SUCCESS " . json_encode($transaction_info), $mode . _LOG_DEBUG);
		return $transaction_info;
	}

	spip_log("call_rembourser_transaction #$id_transaction $uuid (REST) : API REST non configurée", $mode . _LOG_ERREUR);
	return false;
}
