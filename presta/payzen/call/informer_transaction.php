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
 * Récupère la liste des opérations paiement associés à une transaction
 *
 * @param int $id_transaction
 * @param array|string $config
 * @return bool
 */
function presta_payzen_call_informer_transaction_dist($id_transaction, $config = 'payzen'){

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
	$args = [
		'orderId' => $id_transaction,
	];

	// si on a bien configurer le password API GET
	if (payzen_api_password($config)) {
		$response = payzen_api_call($config, "Order/Get", $args);

		if (!$response || empty($response['status'])) {
			return false;
		}

		if ($response['status'] !== 'SUCCESS') {
			$errorCode = (empty($response['answer']['errorCode']) ? 99 : $response['answer']['errorCode']);
			$errorMessage = (empty($response['answer']['errorMessage']) ? '' : $response['answer']['errorMessage']);

			switch ($errorCode) {
				default:
					spip_log("call_informer_transaction #$id_transaction $uuid (REST) : erreur $errorCode $errorMessage", $mode . _LOG_ERREUR);
					return false;
			}
		}

		if (empty($response['answer'])) {
			spip_log("call_informer_transaction #$id_transaction $uuid (REST) : Réponse sans 'answer'", $mode . _LOG_ERREUR);
			return false;
		}

		$transaction_infos = $response['answer'];
		$nb = count($transaction_infos['transactions']);
		$uuids = implode(', ', array_column($transaction_infos['transactions'], 'uuid'));
		spip_log("call_informer_transaction #$id_transaction $uuid (REST) : $nb opérations trouvées ($uuids)", $mode . _LOG_DEBUG);

		/**
		 [
		   "orderId" => "xxx",
		   "shopId" => "xxx",
	       "transactions" => [
		     0 => ...
		     ...
		   ]
		 ]
		 */

		return $transaction_infos;
	}

	spip_log("call_informer_transaction #$id_transaction $uuid (REST) : API REST non configurée", $mode . _LOG_ERREUR);
	return false;
}
