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


include_spip('presta/stripe/inc/stripe');

/**
 * Preparation de la requete par cartes
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param $config
 *   configuration du module
 * @param string $type
 *   type de paiement : acte ou abo
 * @return array|false
 */
function presta_stripe_call_request_dist($id_transaction, $transaction_hash, $config, $type = "acte"){
	$mode = 'stripe';
	if (!is_array($config) OR !isset($config['type']) OR !isset($config['presta'])){
		spip_log("call_request : config invalide " . var_export($config, true), $mode . _LOG_ERREUR);
		return false;
	}
	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	$cartes = array('card');
	if (isset($config['cartes']) AND $config['cartes']){
		$cartes = $config['cartes'];
	}
	$c = $config;
	$c['type'] = ($type !== 'abo' ? 'acte' : 'abo');
	$cartes_possibles = stripe_available_cards($c);

	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction) . " AND transaction_hash=" . sql_quote($transaction_hash))){
		spip_log("call_request : transaction $id_transaction / $transaction_hash introuvable", $mode . _LOG_ERREUR);
		return false;
	}
	
	// On peut maintenant connaître la devise et ses infos
	$devise = $row['devise'];
	$devise_info = bank_devise_info($devise);
	if (!$devise_info) {
		spip_log("Transaction #$id_transaction : la devise $devise n’est pas connue", $mode . _LOG_ERREUR);
		return false;
	}

	if (!$row['id_auteur']
		AND isset($GLOBALS['visiteur_session']['id_auteur'])
		AND $GLOBALS['visiteur_session']['id_auteur']){
		sql_updateq("spip_transactions",
			array("id_auteur" => intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=" . intval($id_transaction)
		);
	}

	// si c'est un abonnement, verifier qu'on saura le traiter vu les limitations de Stripe
	// c'est un abonnement
	$echeance = null;
	if ($type==='abo'){
		// on decrit l'echeance
		if (
			$decrire_echeance = charger_fonction("decrire_echeance", "abos", true)
			AND $echeance = $decrire_echeance($id_transaction)){
			if ($echeance['montant']>0){

				// si plus d'une echeance initiale prevue on ne sait pas faire avec Stripe
				if (isset($echeance['count_init']) AND $echeance['count_init']>1 AND ($echeance['montant'] != $echeance['montant_init'])){
					spip_log("Abonnement Transaction #$id_transaction : nombre d'echeances init " . $echeance['count_init'] . ">1 non supporte", $mode . _LOG_ERREUR);
					return false;
				}

				// si nombre d'echeances limitees, on ne sait pas faire avec Stripe
				if (isset($echeance['count']) AND $echeance['count']>0){
					spip_log("Abonnement Transaction #$id_transaction : nombre d'echeances " . $echeance['count'] . ">0 non supporte", $mode . _LOG_ERREUR);
					return false;
				}

				// on ne sait pas faire une date de debut decalee dans le futur
				if (isset($echeance['date_start']) AND $echeance['date_start'] AND strtotime($echeance['date_start'])>time()){
					spip_log("Abonnement Transaction #$id_transaction : date_start " . $echeance['date_start'] . " non supportee", $mode . _LOG_ERREUR);
					return false;
				}

			}
		}
		//ray($echeance);
		if (!$echeance){
			return false;
		}

	}

	$billing = bank_porteur_infos_facturation($row);
	$email = $billing['email'];

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$montant = bank_formatter_montant_selon_fraction($row['montant'], $devise_info['fraction'], 3);

	include_spip('inc/filtres_mini'); // url_absolue

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
	);
	if ($type==='abo'){
		$contexte['abo'] = 1;
	}
	$contexte['sign'] = bank_sign_response_simple($mode, $contexte);

	$url_success = bank_url_api_retour($config, "response");
	$url_cancel = bank_url_api_retour($config, "cancel");
	foreach ($contexte as $k => $v){
		$url_success = parametre_url($url_success, $k, $v, '&');
		$url_cancel = parametre_url($url_cancel, $k, $v, '&');
	}

	$contexte['action'] = str_replace('&', '&amp;', $url_success);
	$contexte['email'] = $email;
	$contexte['amount'] = $montant;
	$contexte['currency'] = strtolower($devise_info['code']);
	$contexte['key'] = ($config['mode_test'] ? $config['PUBLISHABLE_KEY_test'] : $config['PUBLISHABLE_KEY']);
	$contexte['name'] = bank_nom_site();
	$contexte['description'] = _T('bank:titre_transaction') . '#' . $id_transaction;
	$contexte['image'] = find_in_path('img/logo-paiement-stripe.png');

	$description = bank_description_transaction($id_transaction, $row);
	$item = [
		'name' => $description['libelle'],
		'description' => $description['description'],
		'amount' => $contexte['amount'],
		'currency' => $contexte['currency'],
		'quantity' => 1,
	];

	if (!$contexte['image']){
		$chercher_logo = charger_fonction('chercher_logo', 'inc');
		if ($logo = $chercher_logo(0, 'site')){
			$logo = reset($logo);
			$contexte['image'] = $logo;
		}
	}
	if ($contexte['image']){
		$contexte['image'] = url_absolue($contexte['image']);
		$item['images'] = [$contexte['image']];
	}

	stripe_init_api($config);
	stripe_set_webhook($config);

	$checkout_customer = null;
	// essayer de retrouver un customer existant pour l'id_auteur
	// sinon Stripe creera un nouveau customer
	if ($row['id_auteur']){
		$config_id = bank_config_id($config);
		$customer_id = sql_getfetsel('pay_id', 'spip_transactions',
			'pay_id!=' . sql_quote('') . ' AND id_auteur=' . intval($row['id_auteur']) . ' AND statut=' . sql_quote('ok') . ' AND mode=' . sql_quote("$mode/$config_id"),
			'', 'date_paiement DESC', '0,1');
		if ($customer_id){
			try {
				$customer = \Stripe\Customer::retrieve($customer_id);
				if ($customer and $customer->email===$contexte['email']){
					$checkout_customer = $customer_id;
				}
			} catch (Exception $e) {
				// On ignore silencieusement cette erreur
			}
		}
	}
	if (!$checkout_customer){
		// TODO : creer un customer avec les billing infos
	}


	$payment_types = array_intersect($cartes, array_keys($cartes_possibles));
	if (!$payment_types) {
		$payment_types = ['card'];
	}

	// acte : utiliser une checkout session
	if ($type==='acte'){
		$session_desc = [
			'payment_method_types' => $payment_types,
			'mode' => 'payment',
			'line_items' => [
				[
					'price_data' => [
						'unit_amount' => $item['amount'],
						'currency' => $item['currency'],
						'product_data' => [
							'name' => $item['name'],
							'description' => $item['description'],
						]
					],
					'quantity' => 1,
				]
			],
			// transfer the session id to the success URL
			'success_url' => $url_success . '&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url' => $url_success, // on revient sur success aussi car response gerera l'echec du fait de l'absence de session_id
			'locale' => $GLOBALS['spip_lang'],
		];
		if (!empty($item['images'])) {
			$session_desc['line_items'][0]['price_data']['product_data']['images'] = $item['images'];
		}

		if (!$checkout_customer){
			$session_desc['customer_email'] = $contexte['email'];
		} else {
			$session_desc['customer'] = $checkout_customer;
		}

		try {
			$session = \Stripe\Checkout\Session::create($session_desc);
		}
		catch (Exception $e) {
			spip_log($s = "call_request: Erreur lors de la creation du Checkout\Session acte : ".$e->getMessage(), $mode . _LOG_ERREUR);
			erreur_squelette("[$mode] $s");
			return false;
		}
		//ray($session_desc, $session);

		$contexte['checkout_session_id'] = $session->id;
	}

	// est-ce un abonnement ?
	if ($type === 'abo' and $echeance){
		if ($echeance['montant'] > 0) {

			/*
			 * Create a Price from $item and $echeance
			 * https://stripe.com/docs/api/prices/create
			 */
			$montant_echeance = bank_formatter_montant_selon_fraction($echeance['montant'], $devise_info['fraction'], 3);
			$montant_initial = $montant_echeance;
			if (isset($echeance['montant_init'])) {
				$montant_initial = bank_formatter_montant_selon_fraction($echeance['montant_init'], $devise_info['fraction'], 3);
			}

			$interval = 'month';
			if (!empty($echeance['freq'])) {
				switch ($echeance['freq']) {
					case "day":
					case "dayly":
						// debug purpose only, not fully supported by the bank plugin
						$interval = 'day';
						break;
					case "week":
					case "weekly":
						// debug purpose only, not fully supported by the bank plugin
						$interval = 'week';
						break;
					case "year":
					case "yearly":
						$interval = 'year';
						break;
					default:
						$interval = 'month';
				}
			}

			$session_desc = [
				'payment_method_types' => $payment_types,
				'mode' => 'subscription',
				'line_items' => [],
				// transfer the session id to the success URL
				'success_url' => $url_success . '&session_id={CHECKOUT_SESSION_ID}',
				'cancel_url' => $url_success, // on revient sur success aussi car response gerera l'echec du fait de l'absence de session_id
				'locale' => $GLOBALS['spip_lang'],
			];
			if (!$checkout_customer){
				$session_desc['customer_email'] = $contexte['email'];
			} else {
				$session_desc['customer'] = $checkout_customer;
			}

			$desc_item = [
				'price_data' => [
					'currency' => $contexte['currency'],
					'unit_amount' => $montant_echeance,
					'recurring' => [
						'interval' => $interval,
						'interval_count' => 1,
						//'trial_period_days' => 0, // default
					],
					//'billing_scheme' => 'per_unit', // implicite, non modifiable via price_data
					'product_data' => [
						'name' => $item['name'],
						'description' => $item['description'],
					]
				],
				'quantity' => 1
			];

			// toutes les echeances sont identiques : on cree un unique price et vogue la galere
			// https://stripe.com/docs/billing/migration/migrating-prices
			if ($montant_echeance === $montant_initial) {

				$session_desc['line_items'][] = $desc_item;
				//ray("Echeance unique : ",$session_desc);
			}
			elseif (intval($montant_initial) < intval($montant_echeance)) {
				$session_desc['line_items'][] = $desc_item;

				$montant_remise = bank_formatter_montant_selon_fraction($echeance['montant'] - $echeance['montant_init'], $devise_info['fraction'], 3);

				// et on ajoute une remise pour la premiere echeance
				// et on ajoute un coupon pour la premiere echeance de l'abonnement
				$coupon = \Stripe\Coupon::create([
					'currency' => $contexte['currency'],
					'amount_off' => $montant_remise,
					'duration' => 'once'
				]);
				$session_desc['discounts'][0]['coupon'] = $coupon->id;
				//ray("Première echeance reduite : ",$session_desc);
			}
			elseif (intval($montant_initial) > intval($montant_echeance)) {

				$montant_surcharge = bank_formatter_montant_selon_fraction($echeance['montant_init'] - $echeance['montant'], $devise_info['fraction'], 3);

				// et on ajoute une surcharge pour la premiere echeance
				$desc_item_first = [
					'price_data' => [
						'currency' => $contexte['currency'],
						'unit_amount' => $montant_surcharge,
						'product_data' => [
							'name' => "1ère échéance complément ". $item['name'],
							'description' => $item['description'],
						]
					],
					'quantity' => 1
				];

				$session_desc['line_items'][] = $desc_item_first;
				$session_desc['line_items'][] = $desc_item;
				//ray("Première echeance plus elevee : ",$session_desc);
			}


			try {
				$session = \Stripe\Checkout\Session::create($session_desc);
			}
			catch (Exception $e) {
				spip_log($s = "call_request: Erreur lors de la creation du Checkout\Session abonnement : ".$e->getMessage(), $mode . _LOG_ERREUR);
				erreur_squelette("[$mode] $s");
				return false;
			}

			$contexte['checkout_session_id'] = $session->id;
			//ray($session_desc, $session);
		}

	}


	//ray("call_request stripe", $contexte);
	return $contexte;
}
