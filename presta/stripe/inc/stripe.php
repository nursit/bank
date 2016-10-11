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

include_spip('inc/bank');

/**
 * Initialiser l'API Stripe : chargement de la lib et inits des static
 * @param $config
 */
function stripe_init_api($config){

	include_spip('presta/stripe/lib/stripe-php-4.0.0/init');

	// Set secret key
	// See keys here: https://dashboard.stripe.com/account/apikeys
	$key = ($config['mode_test']?$config['SECRET_KEY_test']:$config['SECRET_KEY']);
	\Stripe\Stripe::setApiKey($key);

	// debug : pas de verif des certificats
	\Stripe\Stripe::$verifySslCerts = false;

	// s'annoncer fierement : SPIP + bank vx
	\Stripe\Stripe::$appInfo = bank_annonce_version_plugin('array');

}


function stripe_traite_reponse_transaction($config, $response) {

	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']) $mode .= "_test";
	$config_id = bank_config_id($config);
	$is_abo = (isset($response['abo']) and $response['abo']);

	if (!isset($response['id_transaction']) OR !isset($response['transaction_hash'])){
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => var_export($response,true),
			)
		);
	}
	if (!isset($response['token']) OR !$response['token']){
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "token absent dans la reponse",
				'log' => var_export($response,true),
			)
		);
	}

	$id_transaction = $response['id_transaction'];
	$transaction_hash = $response['transaction_hash'];

	if (!$row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction))){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction non trouvee",
				'log' => var_export($response,true),
			)
		);
	}
	if ($transaction_hash!=$row['transaction_hash']){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "hash $transaction_hash non conforme",
				'log' => var_export($response,true),
			)
		);
	}

	$montant = intval(round(100*$row['montant'],0));
	if (strlen($montant)<3)
		$montant = str_pad($montant,3,'0',STR_PAD_LEFT);
	$email = bank_porteur_email($row);

	// preparer le paiement
	$desc_charge = array(
		'amount' => $montant,
		"currency" => "eur",
	  "source" => $response['token'],
	  "description" => "Transaction #".$id_transaction,
		"receipt_email" => $email,
		"metadata" => array(
			'id_transaction' => $id_transaction,
			'id_auteur' => $row['id_auteur'],
		),
	);


	// ok, on traite le reglement
	$date=$_SERVER['REQUEST_TIME'];
	$date_paiement = date('Y-m-d H:i:s', $date);

	$erreur = "";
	$erreur_code = 0;

	// charger l'API Stripe avec la cle
	stripe_init_api($config);

	// est-ce un abonnement ?

	$now = time();
	// c'est un abonnement
	if ($is_abo){
		// on decrit l'echeance
		if (
			$decrire_echeance = charger_fonction("decrire_echeance","abos",true)
		  AND $echeance = $decrire_echeance($id_transaction)){
			if ($echeance['montant']>0){

				$montant_echeance = intval(round(100*$echeance['montant'],0));
				if (strlen($montant_echeance)<3)
					$montant_echeance = str_pad($montant_echeance,3,'0',STR_PAD_LEFT);

				$interval = 'month';
				if (isset($echeance['freq']) AND $echeance['freq']=='yearly'){
					$interval = 'year';
				}

				$desc_plan = array(
				  'amount' => $montant_echeance,
				  'interval' => $interval,
				  'name' => $GLOBALS['meta']['adresse_site']." - #$id_transaction",
				  'currency' => $desc_charge['currency'],
				);
				// si une echeance initiale avec montant different, la gerer par un paiement unique maintenant
				// + 1 periode en essai sans paiement sur l'abonnement
				if (isset($echeance['count_init']) AND $echeance['count_init']==1
					AND $montant_echeance !== $montant){

					$time_start = strtotime($date_paiement);
					$time_paiement_1_interval = strtotime("+1 $interval",$time_start);
					$nb_days = ($time_paiement_1_interval - $time_start) / 86400;
					$desc_plan['trial_period_days'] = $nb_days;

				}
				// sinon on annule le paiement unique, inutile
				else {
					$desc_charge['amount'] = 0;
				}
				$desc_plan['id'] = md5(json_encode($desc_plan)."-$transaction_hash");


				try {
					if (!$plan = \Stripe\Plan::retrieve($desc_plan['id'])){
						$plan = \Stripe\Plan::create($desc_plan);
					}

					if (!$plan) {
						$erreur = "Erreur creation plan d'abonnement";
						$erreur_code = "plan_failed";
					}
				} catch (Exception $e) {
					if ($body = $e->getJsonBody()){
						$err  = $body['error'];
						list($erreur_code, $erreur) = stripe_error_code($err);
					}
					else {
						$erreur = $e->getMessage();
						$erreur_code = 'error';
					}
				}

				if ($erreur or $erreur_code) {
					// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
				 	if ($row['reglee']=='oui') return array($id_transaction,true);

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
		}
	}

	// essayer de retrouver ou creer un customer pour l'id_auteur
	$customer = null;
	$customer_id = 0;
	try {
		if ($row['id_auteur']) {
			$customer_id = sql_getfetsel('pay_id','spip_transactions','pay_id!='.sql_quote('').' AND id_auteur='.intval($row['id_auteur']).' AND statut='.sql_quote('ok').' AND mode='.sql_quote("$mode/$config_id"),'','date_paiement DESC','0,1');
			if ($customer_id){
				$customer = \Stripe\Customer::retrieve($customer_id);
			}
		}
		// si customer retrouve, on ajoute la source et la transaction
		if ($customer and $customer->email===$email) {
			$customer->source = $desc_charge['source'];
			$metadata = $customer->metadata;
			if (!$metadata) $metadata = array();
			if (isset($metadata['id_transaction'])) {
				$metadata['id_transaction'] .= ','.$id_transaction;
			}
			else {
				$metadata['id_transaction'] = $id_transaction;
			}
			$metadata['id_auteur'] = $row['id_auteur'];
			$customer->metadata = $metadata;
			$customer->description = sql_getfetsel('nom','spip_auteurs','id_auteur='.intval($row['id_auteur']));
			$customer->save();
		}
		else {
			$d = array(
				'email' => $email,
				'source' => $desc_charge['source'],
				'metadata' => $desc_charge['metadata'],
			);
			if ($row['id_auteur']) {
				$d['description'] = sql_getfetsel('nom','spip_auteurs','id_auteur='.intval($row['id_auteur']));
			}
			$customer = \Stripe\Customer::create($d);
		}

		if ($is_abo and !$customer){
			$erreur = "Erreur creation customer";
			$erreur_code = "cust_failed";
		}


	} catch (Exception $e) {
		if ($body = $e->getJsonBody()){
			$err  = $body['error'];
			list($erreur_code, $erreur) = stripe_error_code($err);
		}
		else {
			$erreur = $e->getMessage();
			$erreur_code = 'error';
		}
		spip_log("Echec creation/recherche customer transaction #$id_transaction $erreur",$mode._LOG_ERREUR);
	}

	if ($is_abo and ($erreur or $erreur_code)){
		// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
	 	if ($row['reglee']=='oui') return array($id_transaction,true);

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

	// Create a charge if needed: this will charge the user's card
	try {

		// If we have a Customer
		if ($customer and $customer->id) {
			$desc_charge['customer'] = $customer->id;
			$response['pay_id'] = $customer->id; // permet de faire de nouveau paiement sans saisie CB
			unset($desc_charge['source']);
		}

		if ($desc_charge['amount']){
			$charge = \Stripe\Charge::create($desc_charge);
			// pour les logs en cas d'echec
			$r = $charge->getLastResponse()->json;
			$response = array_merge($response, $r);

			if (!$charge){
				$erreur = "Erreur creation charge";
				$erreur_code = "charge_failed";
			}
			elseif (!$charge['paid']) {
				$erreur_code = 'not_paid';
				$erreur = 'echec paiement stripe';
				if ($charge['failure_code'] or $charge['failure_message']) {
					$erreur_code = $charge['failure_code'];
					$erreur = $charge['failure_message'];
				}
			}

		}


	} catch(\Stripe\Error\Card $e) {

		// Since it's a decline, \Stripe\Error\Card will be caught
	  $body = $e->getJsonBody();
	  $err  = $body['error'];
		list($erreur_code, $erreur) = stripe_error_code($err);

	} catch (Exception $e) {
		if ($body = $e->getJsonBody()){
			$err  = $body['error'];
			list($erreur_code, $erreur) = stripe_error_code($err);
		}
		else {
			$erreur = $e->getMessage();
			$erreur_code = 'error';
		}
	}

	if ($is_abo and $plan and $customer){
		$desc_sub = array(
			'customer' => $customer->id,
			'plan' => $plan->id,
			'metadata' => array(
				'id_transaction' => $id_transaction,
			),
		);

		try {
			$sub = \Stripe\Subscription::create($desc_sub);
			if (!$sub){
				$erreur = "Erreur creation subscription";
				$erreur_code = "sub_failed";
			}
			$response['abo_uid'] = $sub->id;
		} catch (Exception $e) {
			if ($body = $e->getJsonBody()){
				$err  = $body['error'];
				list($erreur_code, $erreur) = stripe_error_code($err);
			}
			else {
				$erreur = $e->getMessage();
				$erreur_code = 'error';
			}
		}
	}

	// Ouf, le reglement a ete accepte

	if ($erreur or $erreur_code) {
		// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
	 	if ($row['reglee']=='oui') return array($id_transaction,true);

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


	// on verifie que le montant est bon !
	$montant_regle = 0;
	if ($charge){
		$montant_regle = $charge['amount']/100;
	}
	elseif($sub){
		$montant_regle = $sub->plan->amount;
	}

	if ($montant_regle != $row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".var_export($charge, true),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . '_reglements_partiels');
	}


	if ($charge){
		$transaction = $charge['balance_transaction'];
		$authorisation_id = $charge['id'];
	}
	elseif($sub){
		$transaction = $sub->id;
		$authorisation_id = $plan->id;
	}

	$set = array(
		"autorisation_id" => "$transaction/$authorisation_id",
		"mode" => "$mode/$config_id",
		"montant_regle" => $montant_regle,
		"date_paiement" => $date_paiement,
		"statut"=>'ok',
		"reglee"=>'oui'
	);

	if (isset($response['pay_id'])) {
		$set['pay_id'] = $response['pay_id'];
	}
	if (isset($response['abo_uid'])) {
		$set['abo_uid'] = $response['abo_uid'];
	}

	// type et numero de carte ?
	if ($charge){
		if (isset($charge['source']) and $charge['source']['object']=='card'){
			// par defaut on note carte et BIN6 dans refcb
			$set['refcb'] = '';
			if (isset($charge['source']['brand']))
				$set['refcb'] .= $charge['source']['brand'];

			if (isset($charge['source']['last4']) and $charge['source']['last4'])
				$set['refcb'] .= ' ****'.$charge['source']['last4'];

			$set['refcb'] = trim($set['refcb']);
			// validite de carte ?
			if (isset($charge['source']['exp_month']) AND $charge['source']['exp_year']){
				$set['validite'] = $charge['source']['exp_year'] . "-" . str_pad($charge['source']['exp_month'], 2, '0', STR_PAD_LEFT);
			}
		}
	}

	// il faudrait stocker le $charge aussi pour d'eventuels retour ?
	sql_updateq("spip_transactions", $set, "id_transaction=" . intval($id_transaction));
	spip_log("call_response : id_transaction $id_transaction, reglee", $mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));

	return array($id_transaction,true);

}


function stripe_error_code($err){
	$message = $err['message'];
	$code = $err['type'];
	if ($code === 'card_error') {
		$code = $err['code'];
	}

	return array($code, $message);
}