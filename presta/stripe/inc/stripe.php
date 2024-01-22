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

use Stripe\Exception\ApiErrorException;

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('inc/bank');


/**
 * Liste des cartes moyens de paiement possibles selon la config
 *
 * @param array $config
 * @return array
 */
function stripe_available_cards($config) {

	// https://stripe.com/docs/api/payment_methods/object#payment_method_object-type

	$cartes_possibles = [
		'card' => "CARD.gif",
		'bancontact' => "BANCONTACT.gif",
		'ideal' => "IDEAL.gif",
		// a tester
		//'sepa_debit' => "SEPA_SDD.gif",
	];

	// pas de bancontact ni de ideal en abonnement
	if ($config['type'] === 'abo') {
		unset($cartes_possibles['bancontact']);
		unset($cartes_possibles['ideal']);
	}

	return $cartes_possibles;
}

/**
 * Choix de la langue : on utilise celle du site si connue de stripe, sinon auto
 * @param string $lang
 * @return string
 */
function stripe_locale($lang) {
	if (in_array($lang, [
		"bg", "cs", "da", "de", "el", "en", "en-GB", "es", "es-419", "et", "fi", "fil", "fr", "fr-CA", "hr", "hu", "id", "it", "ja", "ko", "lt", "lv", "ms", "mt", "nb", "nl", "pl", "pt", "pt-BR", "ro", "ru", "sk", "sl", "sv", "th", "tr", "vi", "zh", "zh-HK", "zh-TW"
	])) {
		return $lang;
	}

	return "auto";
}

/**
 * Initialiser l'API Stripe : chargement de la lib et inits des static
 * @param $config
 */
function stripe_init_api($config){

	require_once dirname(__DIR__) . '/vendor/autoload.php';

	// Set secret key
	// See keys here: https://dashboard.stripe.com/account/apikeys
	$key = ($config['mode_test'] ? $config['SECRET_KEY_test'] : $config['SECRET_KEY']);
	\Stripe\Stripe::setApiKey($key);

	// debug : pas de verif des certificats
	\Stripe\Stripe::$verifySslCerts = false;

	// s'annoncer fierement : SPIP + bank vx
	\Stripe\Stripe::$appInfo = bank_annonce_version_plugin('array');

}

/**
 * Creer/updater un webhook pour ce site
 * @param array $config
 */
function stripe_set_webhook($config){
	stripe_init_api($config);
	$mode = $config['presta'];
	$key_webhook_secret = (($config['mode_test']) ? 'WEBHOOK_SECRET_KEY_test' : 'WEBHOOK_SECRET_KEY');
	$has_secret = ((isset($config[$key_webhook_secret]) && $config[$key_webhook_secret]) ? true : false);

	$url_endpoint = bank_url_api_retour($config, "autoresponse");
	$event_endpoint = ["*"];
	$p = strpos($url_endpoint, '.');
	$p = strpos($url_endpoint, '/', $p);
	$base_endpoint = substr($url_endpoint, 0, $p+1);
	spip_log("stripe_set_webhook: endpoint $url_endpoint base $base_endpoint", $mode);

	// verifier que le endpoint n'est pas un localhost, sinon on laisse tomber
	$parts = parse_url($base_endpoint);
	if ($parts['host'] === 'localhost') {
		spip_log("stripe_set_webhook: Impossible de creer un endpoint sur localhost -- request Ignoree", $mode . _LOG_ERREUR);
		return;
	}


	$existing_endpoint_id = null;
	try {
		$list = \Stripe\WebhookEndpoint::all(["limit" => 100]);
	} catch (Exception $e) {
		spip_log("stripe_set_webhook: Impossible de lister les endpoints :: " . $e->getMessage(), $mode . _LOG_ERREUR);
		$list = [];
		// si secret connu, on presume qu'on a deja un endpoint configure
		if ($has_secret) {
			return;
		}
	}

	foreach ($list->data as $endpoint) {
		if ($endpoint->status=='enabled') {
			if (
				strpos($endpoint->url, $GLOBALS['meta']['adresse_site'] . '/') === 0
				|| strpos($endpoint->url, $base_endpoint) === 0
			) {
				// si on ne connait pas le secret du webhook on le disabled et on en cree un nouveau
				if (
					$has_secret
					&& $endpoint->url === $url_endpoint
					&& is_array($endpoint->enabled_events)
					&& (
						!array_diff($endpoint->enabled_events, $event_endpoint)
						|| in_array('*', $endpoint->enabled_events)
					)
				) {
					// endpoint OK, rien a faire
					spip_log("stripe_set_webhook: OK endpoint " . $endpoint->id, $mode);
					return;
				} else {
					if ($has_secret) {
						// Update endpoint
						$new_events = (is_array($endpoint->enabled_events) ? array_merge($event_endpoint, $endpoint->enabled_events) : $event_endpoint);
						// Stripe: * should be alone in the array
						if (in_array('*', $new_events)) {
							$new_events = ['*'];
						}
						$set = ['url' => $url_endpoint, 'enabled_events' => $new_events];
					} else {
						$set = ['disabled' => true];
					}
					try {
						\Stripe\WebhookEndpoint::update($endpoint->id, $set);
						spip_log("stripe_set_webhook: UPDATED endpoint " . $endpoint->id . " " . json_encode($set), $mode);
					} catch (Exception $e) {
						spip_log("stripe_set_webhook: Impossible de modifier le endpoint " . $endpoint->id . " " . json_encode($set) . ' :: ' . $e->getMessage(), $mode . _LOG_ERREUR);
					}
					if ($has_secret) {
						return;
					}
				}
			}
		}
	}

	try {
		// aucun endpoint valide, on en ajoute un
		$set = [
			"url" => $url_endpoint,
			"enabled_events" => $event_endpoint
		];
		$endpoint = \Stripe\WebhookEndpoint::create($set);
		spip_log("stripe_set_webhook: ADDED endpoint " . $endpoint->id . " " . json_encode($set), $mode);
		$secret = $endpoint->secret;

		$config_meta = lire_config("bank_paiement/", []);
		$key_ref = (($config['mode_test']) ? 'SECRET_KEY_test' : 'SECRET_KEY');
		if (is_array($config_meta)){
			foreach ($config_meta as $k => $v){
				if (strncmp($k, "config_", 7)==0){
					if ($v['presta']==='stripe'
						and $v['mode_test']==$config['mode_test']
						and $v[$key_ref]===$config[$key_ref]){
						ecrire_config("bank_paiement/$k/$key_webhook_secret", $secret);
					}
				}
			}
		}

	} catch (Exception $e) {
		spip_log("stripe_set_webhook: Impossible de creer un endpoint :: " . $e->getMessage(), $mode . _LOG_ERREUR);
	}

}

/**
 * Retrouver une transaction par son customer_id + payment_intent_id + date payment_intent
 * necessaire pour traiter les webhooks sur les payment_intent qui ne donnent pas d'autres information
 * on a stocké le(s) checkout_session_id généré pour une transaction dans le champ token
 * @param array $config
 * @param string $customer_id
 * @param string $payment_intent_id
 * @param string $date
 * @return array|false
 */
function stripe_retrouve_transaction_par_payment_et_customer($config, $customer_id, $payment_intent_id, $date) {

	if (!empty($customer_id) and !empty($payment_intent_id) and $t = strtotime($date)) {

		$where = [
			"date_transaction>=".sql_quote(date('Y-m-d H:i:s', strtotime('-48hours', $t))),
			"date_transaction<=".sql_quote(date('Y-m-d H:i:s', strtotime('+2hours', $t))),
			'pay_id='.sql_quote($customer_id),
		];
		$transactions = sql_allfetsel('*', 'spip_transactions', $where, '', 'date_transaction');
		foreach ($transactions as $transaction) {
			if (!empty($transaction['token']) and $tokens = json_decode($transaction['token'], true)) {
				foreach ($tokens as $checkout_session_id) {
					if ($checkout_session = \Stripe\Checkout\Session::retrieve($checkout_session_id)) {
						if (!empty($checkout_session->payment_intent)
						  and $checkout_session->payment_intent === $payment_intent_id) {
							return [$transaction, $checkout_session];
						}
					}
				}
			}
		}
	}

	return false;
}

/**
 * Gerer la reponse du POST JS sur paiement/abonnement
 *
 * @param array $config
 * @param array $response
 * @return array
 */
function stripe_traite_reponse_transaction($config, &$response) {

	$mode = $config['presta'];
	if (isset($config['mode_test']) && $config['mode_test']) {
		$mode .= "_test";
	}
	$config_id = bank_config_id($config);
	$is_abo = (isset($response['abo']) && $response['abo']);

	if (!isset($response['id_transaction']) || !isset($response['transaction_hash'])) {
		return bank_transaction_invalide(0,
			[
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => var_export($response, true),
			]
		);
	}
	if ((!isset($response['payment_id']) || !$response['payment_id'])) {
		return bank_transaction_invalide(0,
			[
				'mode' => $mode,
				'erreur' => "payment_id absent dans la reponse",
				'log' => var_export($response, true),
			]
		);
	}

	$id_transaction = $response['id_transaction'];
	$transaction_hash = $response['transaction_hash'];

	if (!$row = sql_fetsel('*', 'spip_transactions', 'id_transaction=' . intval($id_transaction))) {
		return bank_transaction_invalide($id_transaction,
			[
				'mode' => $mode,
				'erreur' => "transaction non trouvee",
				'log' => var_export($response, true),
			]
		);
	}
	if ($transaction_hash != $row['transaction_hash']) {
		return bank_transaction_invalide($id_transaction,
			[
				'mode' => $mode,
				'erreur' => "hash $transaction_hash non conforme",
				'log' => var_export($response, true),
			]
		);
	}

	// On peut maintenant connaître la devise et ses infos
	$devise_fraction = bank_devise_info($row['devise'], 'fraction');

	// ok, on traite le reglement
	$date = $_SERVER['REQUEST_TIME'];
	$date_paiement = date('Y-m-d H:i:s', $date);

	$erreur = '';
	$erreur_code = 0;

	// charger l'API Stripe avec la cle
	stripe_init_api($config);

	try {
		$payment = \Stripe\PaymentIntent::retrieve([
			'id' => $response['payment_id'],
			'expand' => ['latest_charge'],
		]);
	} catch (ApiErrorException $e) {
		if ($body = $e->getJsonBody()) {
			$err = $body['error'];
			list($erreur_code, $erreur) = stripe_error_code($err);
		} else {
			$erreur = $e->getMessage();
			$erreur_code = 'error';
		}
	}

	if (!$payment || !in_array($payment->status, [/*'processing', */ 'succeeded'])) {
		// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
		if ($row['reglee'] === 'oui') {
			return [$id_transaction, true];
		}
		if (!empty($payment) and !empty($payment->last_payment_error)
		) {
			$erreur = trim((implode(' ', [$payment->last_payment_error->code ?? '', $payment->last_payment_error->doc_url ?? '', $payment->last_payment_error->message ?? ''])))
			 . ($erreur ? "\n$erreur" : "");
		}
		// sinon enregistrer l'absence de paiement et l'erreur
		return bank_transaction_echec($id_transaction,
			[
				'mode' => $mode,
				'config_id' => $config_id,
				'date_paiement' => $date_paiement,
				'erreur' => ($payment ? "Status PaymentIntent=" . $payment->status : "PaymentIntent " . $response['payment_id'] . " non valide") . ($erreur ? "\n$erreur" : ""),
				'code_erreur' => $erreur_code,
				'log' => var_export($response, true),
			]
		);
	}

	// essayer de retrouver ou creer un customer pour l'id_auteur
	if (empty($response['pay_id']) && ($customer_id = $payment->customer)) {
		$response['pay_id'] = $customer_id;
	}

	// Ouf, le reglement a ete accepte

	// on verifie que le montant est bon !
	$montant_regle = $payment->amount_received / (10**$devise_fraction);

	if ($montant_regle!=$row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=" . $row['montant'] . ":" . var_export($payment, true), $mode);
		// on log ca dans un journal dedie
		spip_log($t, $mode . '_reglements_partiels');
	}

	$autorisation_id = $payment->id;
	$transaction = "";
	$charge = null;
	if ($payment->latest_charge) {
		$charge = $payment->latest_charge;
		$transaction = $charge['balance_transaction'];
		$date_paiement = date('Y-m-d H:i:s', $charge['created']);
	}

	$set = [
		"autorisation_id" => "$transaction/$autorisation_id",
		"mode" => "$mode/$config_id",
		"montant_regle" => $montant_regle,
		"date_paiement" => $date_paiement,
		"statut" => 'ok',
		"reglee" => 'oui'
	];

	if (isset($response['pay_id'])) {
		$set['pay_id'] = $response['pay_id'];
	}
	if (isset($response['abo_uid'])) {
		$set['abo_uid'] = $response['abo_uid'];
	}

	// type et numero de carte ?
	$card = null;
	if (
		$charge
		&& isset($charge['payment_method_details'])
		&& $charge['payment_method_details']['type'] === 'card'
	) {
		$card = $charge['payment_method_details']['card'];
	}
	if (
		!$card
		&& $charge
		&& isset($charge['source'])
		&& $charge['source']['object'] === 'card'
	) {
		$card = $charge['source'];
	}
	if (!$card) {
		// TODO utiliser $payment->payment_method
	}

	if ($card) {
		// par defaut on note carte et BIN6 dans refcb
		$set['refcb'] = '';
		if (isset($card['brand'])) {
			$set['refcb'] .= $card['brand'];
		}

		if (isset($card['last4']) && $card['last4']) {
			$set['refcb'] .= ' ****' . $card['last4'];
		}

		$set['refcb'] = trim($set['refcb']);
		// validite de carte ?
		if (isset($card['exp_month']) && isset($card['exp_year'])) {
			$set['validite'] = $card['exp_year'] . "-" . str_pad($card['exp_month'], 2, '0', STR_PAD_LEFT);
		}
	}

	$response = array_merge($response, $set);

	sql_updateq("spip_transactions", $set, "id_transaction=" . intval($id_transaction));
	spip_log("call_response : id_transaction $id_transaction, reglee", $mode);

	// faire les updates chez Stripe, *apres* avoir mis la transaction a jour
	// pour faire au plus vite et mieux gerer les concurrences
	if (!empty($response['pay_id'])) {
		try {
			$customer = \Stripe\Customer::retrieve($response['pay_id']);

			// si customer retrouve, on ajoute la source et la transaction
			if ($customer) {
				$metadata = $customer->metadata;
				if (!$metadata){
					$metadata = [];
				}
				if (isset($metadata['id_transaction'])) {
					$metadata['id_transaction'] .= ',' . $id_transaction;
				} else {
					$metadata['id_transaction'] = $id_transaction;
				}

				// limiter la longueur de id_transaction sinon Stripe fait une erreur
				// on ne garde que les plus recentes
				while (strlen($metadata['id_transaction']) > 256) {
					$t = explode(',', $metadata['id_transaction']);
					array_shift($t);
					$metadata['id_transaction'] = implode(',', $t);
				}

				if ($row['id_auteur']>0) {
					$metadata['id_auteur'] = $row['id_auteur'];
					$customer->metadata = $metadata;
					$customer->description = sql_getfetsel('nom', 'spip_auteurs', 'id_auteur=' . intval($row['id_auteur']));
				}
				$customer->save();
			}
		} catch (ApiErrorException $e) {
			if ($body = $e->getJsonBody()){
				$err = $body['error'];
				list($erreur_code, $erreur) = stripe_error_code($err);
			} else {
				$erreur = $e->getMessage();
			}
			spip_log("Echec recherche/update customer transaction #$id_transaction $erreur", $mode . _LOG_ERREUR);
		}
	}

	if ($charge && $payment){
		try {
			\Stripe\Charge::update($charge->id, ['description' => $payment->description,]);
		} catch (Exception $e) {
			spip_log('call_response: erreur lors de la modification de la charge ' . $charge->id . ' :: ' . $e->getMessage(), $mode . _LOG_ERREUR);
		}
	}


	$options = ['row_prec' => $row];
	if (!empty($response['lang'])) {
		$options['lang'] = $response['lang'];
	}
	$regler_transaction = charger_fonction('regler_transaction', 'bank');
	$regler_transaction($id_transaction, $options);

	// update payment informations for Stripe Dashboard
	// after billing
	try {
		$description = bank_description_transaction($id_transaction);
		$description = array_filter([$description['libelle'], $description['description']]);
		$description = implode(" | ", $description);
		$description = str_replace("\n", " ", $description);
		$description = str_replace("\r", " ", $description);
		$nom_site = bank_nom_site();
		$description .= " [$nom_site]";
		$payment->description = $description;
		$metadata = $payment->metadata;
		if (!$metadata){
			$metadata = [];
		}
		$metadata['id_transaction'] = $id_transaction;
		$metadata['id_auteur'] = $row['id_auteur'];
		$metadata['nom_site'] = $nom_site;
		$metadata['url_site'] = $GLOBALS['meta']['adresse_site'];
		$payment->save();
	} catch (ApiErrorException $e) {
		if ($body = $e->getJsonBody()) {
			$err = $body['error'];
			list($erreur_code, $erreur) = stripe_error_code($err);
		} else {
			$erreur = $e->getMessage();
			$erreur_code = 'error';
		}
		spip_log("Echec update payment metadata/description transaction #$id_transaction $erreur", $mode . _LOG_ERREUR);
	}

	return [$id_transaction, true];

}


function stripe_error_code($err) {
	$message = $err['message'];
	$code = $err['type'];
	if ($code === 'card_error') {
		$code = $err['code'];
	}

	return [$code, $message];
}