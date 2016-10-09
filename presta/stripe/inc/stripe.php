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


function stripe_traite_reponse_transaction($config, $response) {
	include_spip('presta/stripe/lib/stripe-php-4.0.0/init');

	$mode = $config['presta'];
	$config_id = bank_config_id($config);

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

	$key = ($config['mode_test']?$config['SECRET_KEY_test']:$config['SECRET_KEY']);
	$c = array(
		'amount' => $montant,
		"currency" => "eur",
	  "source" => $response['token'],
	  "description" => "Transaction #".$id_transaction,
		"metadata" => array(
			"id_transaction" => $id_transaction
		),
	);

	// ok, on traite le reglement
	$date=$_SERVER['REQUEST_TIME'];
	$date_paiement = date('Y-m-d H:i:s', $date);

	$erreur = "";
	$erreur_code = 0;

	// Set your secret key
	// See your keys here: https://dashboard.stripe.com/account/apikeys
	\Stripe\Stripe::setApiKey($key);
	// debug
	\Stripe\Stripe::$verifySslCerts = false;

	// Create a charge: this will charge the user's card
	try {

	  $charge = \Stripe\Charge::create($c);
		var_dump($charge);
		die();
		$response = array_merge($response, $charge);

	} catch(\Stripe\Error\Card $e) {

		var_dump($e);
		die();

		// Since it's a decline, \Stripe\Error\Card will be caught
	  $body = $e->getJsonBody();
	  $err  = $body['error'];

		list($erreur_code, $erreur) = stripe_error_code($err);
	}

	// Ouf, le reglement a ete accepte

	if (!$erreur_code and !$charge['paid']) {
		$erreur_code = 'not_paid';
		$erreur = 'echec paiement stripe';
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


	// on verifie que le montant est bon !
	$montant_regle = $charge['amount']/100;

	if ($montant_regle != $row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".var_export($charge, true),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . '_reglements_partiels');
	}


	$transaction = $charge['id'];
	$authorisation_id = $charge['balance_transaction'];

	$set = array(
		"autorisation_id" => "$transaction/$authorisation_id",
		"mode" => "$mode/$config_id",
		"montant_regle" => $montant_regle,
		"date_paiement" => $date_paiement,
		"statut"=>'ok',
		"reglee"=>'oui'
	);

	// type et numero de carte ?
	if (isset($charge['source']) and $charge['source']['object']=='card'){
		// par defaut on note carte et BIN6 dans refcb
		$set['refcb'] = '';
		if (isset($charge['source']['brand']))
			$set['refcb'] .= $charge['source']['brand'];
		$set['refcb'] = trim($set['refcb']);
		// validite de carte ?
		if (isset($charge['source']['exp_month']) AND $charge['source']['exp_year']){
			$set['validite'] = $charge['source']['exp_year'] . "-" . str_pad($charge['source']['exp_month'], 2, '0', STR_PAD_LEFT);
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