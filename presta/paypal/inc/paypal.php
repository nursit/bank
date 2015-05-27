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


/**
 * Determiner le mode test en fonction d'un define ou de la config
 * @param array $config
 * @return bool
 */
function paypal_is_sandbox($config){
	$test = false;
	// _PAYPAL_SANDBOX force a TRUE pour utiliser l'adresse de test de CMCIC
	if ( (defined('_PAYPAL_SANDBOX') AND _PAYPAL_SANDBOX)
	  OR (isset($config['mode_test']) AND $config['mode_test']) ){
		$test = true;
	}
	return $test;
}

/**
 * Determiner l'URL d'appel serveur en fonction de la config
 *
 * @param array $config
 * @return string
 */
function paypal_url_serveur($config){

	if (paypal_is_sandbox($config)){
		return "https://www.sandbox.paypal.com:443/fr/cgi-bin/webscr";
	}
	else {
		return "https://www.paypal.com:443/fr/cgi-bin/webscr";
	}
}


/**
 * Recevoir la notification paypal
 * du paiement
 *
 * @param array $config
 * @param array $response
 * @return array
 */
function paypal_traite_response($config, $response){

	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']) $mode .= "-test";
	$config_id = bank_config_id($config);
	spip_log('Paypal IPN '.var_export($response,true),$mode);
		
	if (!isset($response['receiver_email']) OR ($response['receiver_email']!=$config['BUSINESS_USERNAME'])){
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "receiver_email errone",
				'log' => var_export($response, true),
			)
		);
	}

	if (!isset($response['invoice'])){
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "pas de invoice specifie",
				'log' => var_export($response, true),
			)
		);
	}

	if (strpos($response['invoice'],"|")!==false){
		list($id_transaction,$transaction_hash) = explode('|',$response['invoice']);
	}
	else {
		list($id_transaction,$transaction_hash) = explode('-',$response['invoice']);
	}
	if (!$row = sql_fetsel("*","spip_transactions", "id_transaction=".intval($id_transaction) ." AND transaction_hash=".sql_quote($transaction_hash))){
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => var_export($response, true),
			)
		);
	}

	if ($row['reglee']=='oui')
		return array($id_transaction,true); // cette transaction a deja ete reglee. double entree, on ne fait rien

	// verifier que le status est bien ok
	if (!isset($response['payment_status']) OR ($response['payment_status']!='Completed')){
		return bank_transaction_echec($id_transaction,
			array(
				'mode' => $mode,
				'config_id' => $config_id,
				'erreur' => "payment_status=".$response['payment_status'],
				'log' => var_export($response, true),
			)
		);
	}
	
	// verifier que le numero de transaction au sens paypal
	// (=numero d'autorisation ici) est bien fourni
	if (!isset($response['txn_id']) OR (!$response['txn_id'])){
		return bank_transaction_echec($id_transaction,
			array(
				'mode' => $mode,
				'config_id' => $config_id,
				'erreur' => "pas de txn_id (autorisation manquante)",
				'log' => var_export($response, true),
			)
		);
	}
	
	// verifier que le numero de transaction au sens paypal
	// (=numero d'autorisation ici) n'a pas deja ete utilise
	$autorisation_id = $response['txn_id'];
	if ($id = sql_getfetsel("id_transaction","spip_transactions","autorisation_id=".sql_quote($autorisation_id)." AND mode='paypal' AND id_transaction<>".intval($id_transaction))){
		return bank_transaction_echec($id_transaction,
			array(
				'mode' => $mode,
				'config_id' => $config_id,
				'erreur' => "txn_id deja en base (doublon autorisation)",
				'log' => var_export($response, true),
			)
		);
	}

	// enregistrer immediatement le present numero d'autorisation pour ne pas risquer des requetes simultanees sur le meme id
	$set = array(
		"autorisation_id"=>$autorisation_id,
		"mode"=>$mode
	);
	sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));

	// une monnaie est-elle bien indique (et en EUR) ?
	if (!isset($response['mc_currency']) OR ($response['mc_currency']!='EUR')){
		return bank_transaction_echec($id_transaction,
			array(
				'mode' => $mode,
				'config_id' => $config_id,
				'erreur' => "devise mc_currency incorrecte",
				'log' => var_export($response, true),
			)
		);
	}

	// un montant est il bien renvoye et correct ?
	if (!isset($response['mc_gross']) OR (($montant_regle=$response['mc_gross'])!=$row['montant'])){
		return bank_transaction_echec($id_transaction,
			array(
				'mode' => $mode,
				'config_id' => $config_id,
				'erreur' => "montant mc_gross incorrect",
				'log' => var_export($response, true),
			)
		);
	}

	// verifier que la notification vien bien de paypal !
	if (!bank_paypal_verifie_notification($config, $response)){
		return bank_transaction_echec($id_transaction,
			array(
				'mode' => $mode,
				'config_id' => $config_id,
				'erreur' => "verification invalide (IPN!=VERIFIE)",
				'log' => var_export($response, true),
			)
		);
	}

	$set = array(
		"autorisation_id"=>$autorisation_id,
		"mode"=>"$mode/$config_id",
		"montant_regle"=>$montant_regle,
		"date_paiement"=>date('Y-m-d H:i:s'),
		"statut"=>'ok',
		"reglee"=>'oui'
	);

	sql_updateq("spip_transactions", $set, "id_transaction=".intval($id_transaction));
	spip_log("simple_reponse : id_transaction $id_transaction, reglee",$mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));
	return array($id_transaction,true);
}

/**
 * Renseigner une transaction echouee
 *
 * @param int $id_transaction
 * @param string $message
 * @return array
 */
function paypal_echec_transaction($id_transaction,$message){
	sql_updateq("spip_transactions",
	  array('message'=>$message,'statut'=>'echec'),
	  "id_transaction=".intval($id_transaction)
	);
	return array($id_transaction,false); // erreur sur la transaction
}

/**
 * Verifier que la notification de paiement vient bien de paypal !
 * @param array $config
 * @param array $response
 * @return bool
 */
function bank_paypal_verifie_notification($config, $response){

	// marche bien avec la notif de test ci-dessous
	/*
	$response = json_decode
    (
        '{
            "residence_country": "US",
            "invoice": "abc1234",
            "address_city": "San Jose",
            "first_name": "John",
            "payer_id": "TESTBUYERID01",
            "shipping": "3.04",
            "mc_fee": "0.44",
            "txn_id": "611422392",
            "receiver_email": "seller@paypalsandbox.com",
            "quantity": "1",
            "custom": "xyz123",
            "payment_date": "22:29:21 28 Oct 2013 PDT",
            "address_country_code": "US",
            "address_zip": "95131",
            "tax": "2.02",
            "item_name": "something",
            "address_name": "John Smith",
            "last_name": "Smith",
            "receiver_id": "seller@paypalsandbox.com",
            "item_number": "AK-1234",
            "verify_sign": "AiPC9BjkCyDFQXbSkoZcgqH3hpacAaChsjNZq2jHG82F97aoFSMa6SED",
            "address_country": "United States",
            "payment_status": "Completed",
            "address_status": "confirmed",
            "business": "seller@paypalsandbox.com",
            "payer_email": "buyer@paypalsandbox.com",
            "notify_version": "2.1",
            "txn_type": "web_accept",
            "test_ipn": "1",
            "payer_status": "verified",
            "mc_currency": "USD",
            "mc_gross": "12.34",
            "address_state": "CA",
            "mc_gross1": "12.34",
            "payment_type": "echeck",
            "address_street": "123, any street"
        }',
        true
    );
	*/

	// lire la publication du systeme PayPal et ajouter 'cmd' en tete
	$post_check = array('cmd' => '_notify-validate');
	foreach($response as $k=>$v){
		$post_check[$k] = $v;
	}

	// envoyer la demande de verif en post
	// attention, c'est une demande en ssl, il faut avoir un php qui le supporte
	$c = $config;
	if (isset($response['test_ipn']) AND $response['test_ipn']){
		$c['mode_test'] = true;
	}
	else {
		$c['mode_test'] = false;
	}
	$url = paypal_url_serveur($c);
	$bank_recuperer_post_https = charger_fonction("bank_recuperer_post_https","inc");
	list($resultat,$erreur,$erreur_msg) = $bank_recuperer_post_https($url,$post_check,isset($post_check['payer_id'])?$post_check['payer_id']:'');


	if (strncmp(trim($resultat),'VERIFIE',7)==0)
		return true;


	spip_log("Retour IPN :$resultat:Erreur $erreur:$erreur_msg: POST: ".http_build_query($post_check),$config['presta']._LOG_ERREUR);

	#var_dump($resultat);
	#var_dump("URL=".$url);
	#var_dump($post_check);
	#die('Echec Validation IPN');

	return false;
}