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
 * @return array
 */
function presta_stripe_call_request_dist($id_transaction, $transaction_hash, $config, $type="acte"){

	$mode = 'stripe';
	if (!is_array($config) OR !isset($config['type']) OR !isset($config['presta'])){
		spip_log("call_request : config invalide ".var_export($config,true),$mode._LOG_ERREUR);
		return "";
	}
	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']) $mode .= "_test";

	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash))){
		spip_log("call_request : transaction $id_transaction / $transaction_hash introuvable",$mode._LOG_ERREUR);
		return "";
	}

	if (!$row['id_auteur']
	  AND isset($GLOBALS['visiteur_session']['id_auteur'])
	  AND $GLOBALS['visiteur_session']['id_auteur']) {
		sql_updateq("spip_transactions",
			array("id_auteur" => intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=" . intval($id_transaction)
		);
	}

	// si c'est un abonnement, verifier qu'on saura le traiter vu les limitations de Stripe
	// c'est un abonnement
	$echeance = null;
	if ($type === 'abo'){
		// on decrit l'echeance
		if (
			$decrire_echeance = charger_fonction("decrire_echeance", "abos", true)
			AND $echeance = $decrire_echeance($id_transaction)){
			if ($echeance['montant']>0){

				// si plus d'une echeance initiale prevue on ne sait pas faire avec Stripe
				if (isset($echeance['count_init']) AND $echeance['count_init']>1){
					spip_log("Transaction #$id_transaction : nombre d'echeances init " . $echeance['count_init'] . ">1 non supporte", $mode . _LOG_ERREUR);
					return "";
				}

				// si nombre d'echeances limitees, on ne sait pas faire avec Stripe
				if (isset($echeance['count']) AND $echeance['count']>0){
					spip_log("Transaction #$id_transaction : nombre d'echeances " . $echeance['count'] . ">0 non supporte", $mode . _LOG_ERREUR);
					return "";
				}

				if (isset($echeance['date_start']) AND $echeance['date_start'] AND strtotime($echeance['date_start'])>time()){
					spip_log("Transaction #$id_transaction : date_start " . $echeance['date_start'] . " non supportee", $mode . _LOG_ERREUR);
					return "";
				}

			}
		}
		if (!$echeance){
			return "";
		}
	}

	
	$email = bank_porteur_email($row);

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$montant = intval(round(100*$row['montant'],0));
	if (strlen($montant)<3)
		$montant = str_pad($montant,3,'0',STR_PAD_LEFT);

	include_spip('inc/filtres_mini'); // url_absolue

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
	);
	if ($type === 'abo'){
		$contexte['abo'] = 1;
	}
	$contexte['sign'] = bank_sign_response_simple($mode, $contexte);

	$url_success = bank_url_api_retour($config,"response");
	$url_cancel = bank_url_api_retour($config,"cancel");
	foreach($contexte as $k=>$v){
		$url_success = parametre_url($url_success, $k, $v, '&');
		$url_cancel = parametre_url($url_cancel, $k, $v, '&');
	}

	$contexte['action'] = str_replace('&', '&amp;', $url_success);
	$contexte['email'] = $email;
	$contexte['amount'] = $montant;
	$contexte['currency'] = 'eur';
	$contexte['key'] = ($config['mode_test']?$config['PUBLISHABLE_KEY_test']:$config['PUBLISHABLE_KEY']);
	$contexte['name'] = bank_nom_site();
	$contexte['description'] = _T('bank:titre_transaction') . '#'.$id_transaction;
	$contexte['image'] = find_in_path('img/logo-paiement-stripe.png');

	$item = [
		'name' => _T('bank:titre_transaction') . " #$id_transaction",
		'amount' => $contexte['amount'],
		'currency' => $contexte['currency'],
    'quantity' => 1,
	];

	if ($id_commande = $row['id_commande']
		and test_plugin_actif('commande')
	  and $ref = sql_getfetsel('reference', 'spip_commandes', 'id_commande='.intval($id_commande))) {
		$item['name'] = _T('commande:commande_numero') . " #$id_commande";
		$item['description'] = _T('commande:commande_reference_numero') . " $ref";
	}
	if (!$contexte['image']) {
		$chercher_logo = charger_fonction('chercher_logo','inc');
		if ($logo = $chercher_logo(0,'site')){
			$logo = reset($logo);
			$contexte['image'] = $logo;
		}
	}
	if ($contexte['image']) {
		$contexte['image'] = url_absolue($contexte['image']);
		$item['images'] = [$contexte['image']];
	}

	stripe_init_api($config);
	stripe_set_webhook($config);

	$checkout_customer = null;
	// essayer de retrouver un customer existant pour l'id_auteur
	// sinon Stripe creera un nouveau customer
	if ($row['id_auteur']) {
		$config_id = bank_config_id($config);
		$customer_id = sql_getfetsel('pay_id', 'spip_transactions',
			'pay_id!=' . sql_quote('') . ' AND id_auteur=' . intval($row['id_auteur']) . ' AND statut=' . sql_quote('ok') . ' AND mode=' . sql_quote("$mode/$config_id"),
			'', 'date_paiement DESC', '0,1');
		if ($customer_id) {
			try {
				$customer = \Stripe\Customer::retrieve($customer_id);
				if ($customer and $customer->email === $contexte['email']) {
					$checkout_customer = $customer_id;
				}
			}
			catch (Exception $e) {
			}
		}
	}


	// acte : utiliser une checkout session
	if ($type === 'acte'){
		$session_desc = [
			'payment_method_types' => ['card'],
			'line_items' => [[$item]],
			// transfer the session id to the success URL
			'success_url' => $url_success . '&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url' => $url_success,
			'locale' => $GLOBALS['spip_lang'],
		];

		if (!$checkout_customer){
			$session_desc['customer_email'] = $contexte['email'];
		} else {
			$session_desc['customer'] = $checkout_customer;
		}

		$session = \Stripe\Checkout\Session::create($session_desc);

		$contexte['checkout_session_id'] = $session->id;
	}

	/*
	// est-ce un abonnement ?
	if ($type === 'abo' and $echeance){
		if ($echeance['montant'] > 0) {

			$montant_echeance = intval(round(100 * $echeance['montant'], 0));
			if (strlen($montant_echeance) < 3) {
				$montant_echeance = str_pad($montant_echeance, 3, '0', STR_PAD_LEFT);
			}

			$interval = 'month';
			if (isset($echeance['freq']) AND $echeance['freq'] == 'yearly') {
				$interval = 'year';
			}

			$desc_plan = array(
				'amount' => $montant_echeance,
				'interval' => $interval,
				'name' => "#$id_transaction [$nom_site]",
				'currency' => $contexte['currency'],
				'metadata' => $desc_charge['metadata'],
			);

			// dans tous les cas on fait preleve la premiere echeance en paiement unique
			// et en faisant démarrer l'abonnement par "1 periode" en essai sans paiement
			// ca permet de gerer le cas paiement initial different, et de recuperer les infos de CB dans tous les cas
			$time_start = strtotime($date_paiement);
			$time_paiement_1_interval = strtotime("+1 $interval", $time_start);
			$nb_days = intval(round(($time_paiement_1_interval - $time_start) / 86400));
			$desc_plan['trial_period_days'] = $nb_days;

			// un id unique (sauf si on rejoue le meme paiement)
			$desc_plan['id'] = md5(json_encode($desc_plan) . "-$transaction_hash");

			try {
				$plan = \Stripe\Plan::retrieve($desc_plan['id']);
			} catch (Exception $e) {
				// erreur si on ne retrouve pas le plan, on ignore
				$plan = false;
			}

			try {
				if (!$plan) {
					$plan = \Stripe\Plan::create($desc_plan);
				}
				if (!$plan) {
					$erreur = "Erreur creation plan d'abonnement";
					$erreur_code = "plan_failed";
				}
			} catch (Exception $e) {
				if ($body = $e->getJsonBody()) {
					$err = $body['error'];
					list($erreur_code, $erreur) = stripe_error_code($err);
				} else {
					$erreur = $e->getMessage();
					$erreur_code = 'error';
				}
			}

			if ($erreur or $erreur_code) {
				// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
				if ($row['reglee'] == 'oui') {
					return array($id_transaction, true);
				}

				// sinon enregistrer l'absence de paiement et l'erreur
				return bank_transaction_echec($id_transaction,
					array(
						'mode' => $mode,
						'config_id' => $config_id,
						'date_paiement' => $date_paiement,
						'code_erreur' => $erreur_code,
						'erreur' => $erreur,
						'log' => var_export($response, true),
					)
				);
			}

		}

		if ($plan and $customer) {
			$desc_sub = array(
				'customer' => $customer->id,
				'plan' => $plan->id,
				'metadata' => array(
					'id_transaction' => $id_transaction,
				),
			);

			try {
				$sub = \Stripe\Subscription::create($desc_sub);
				if (!$sub) {
					$erreur = "Erreur creation subscription";
					$erreur_code = "sub_failed";
				} else {
					$response['abo_uid'] = $sub->id;
				}
			} catch (Exception $e) {
				if ($body = $e->getJsonBody()) {
					$err = $body['error'];
					list($erreur_code, $erreur) = stripe_error_code($err);
				} else {
					$erreur = $e->getMessage();
					$erreur_code = 'error';
				}
			}
		} else {
			$erreur = "Erreur creation subscription (plan or customer missing)";
			$erreur_code = "sub_failed";
		}
	}
  */


	return $contexte;
}
