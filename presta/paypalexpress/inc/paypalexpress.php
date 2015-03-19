<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2014 - Distribue sous licence GNU/GPL
 *
 */

/****************************************************
 * CallerService.php
 *
 * This file uses the constants.php to get parameters needed
 * to make an API call and calls the server.if you want use your
 * own credentials, you have to change the constants.php
 *
 * Called by TransactionDetails.php, ReviewOrder.php,
 * DoDirectPaymentReceipt.php and DoExpressCheckoutPayment.php.
 ****************************************************/


function bank_paypalexpress_order_init($id_transaction, $url_confirm=null){

	if (!$row = sql_fetsel('*', 'spip_transactions', 'id_transaction=' . intval($id_transaction))){
		spip_log("Erreur transaction $id_transaction introuvable", 'paypalexpress' . _LOG_ERREUR);
		return false;
	}

	if ($row['reglee']=='oui'){
		spip_log("Erreur transaction $id_transaction deja reglee", 'paypalexpress' . _LOG_ERREUR);
		return false;
	}

	// pour le retour
	$_SESSION['id_transaction'] = $id_transaction;
	$_SESSION['paypalexpress_url_confirm'] = ($url_confirm?$url_confirm:self());


	/* The servername and serverport tells PayPal where the buyer
	should be directed back to after authorizing payment.
	In this case, its the local webserver that is running this script
	Using the servername and serverport, the return URL is the first
	portion of the URL that buyers will return to after authorizing payment
	*/
	$paymentAmount = $row['montant'];
	$currencyCodeType = "EUR";
	$paymentType = "Sale";


	/* The returnURL is the location where buyers return when a
	payment has been succesfully authorized.
	The cancelURL is the location buyers are sent to when they hit the
	cancel button during authorization of payment during the PayPal flow
	*/

	$returnURL = generer_url_action('bank_response', "bankp=paypalexpress", true, true);
	$cancelURL = generer_url_action('bank_cancel', "bankp=paypalexpress", true, true);

	/* Construct the parameter string that describes the PayPal payment
	the varialbes were set in the web form, and the resulting string
	is stored in $nvpstr
	*/

	$nvpstr = "&Amt=" . $paymentAmount . "&PAYMENTACTION=" . $paymentType . "&ReturnUrl=" . urlencode($returnURL) . "&CANCELURL=" . urlencode($cancelURL) . "&CURRENCYCODE=" . $currencyCodeType;

	/* Make the call to PayPal to set the Express Checkout token
	If the API call succeded, then redirect the buyer to PayPal
	to begin to authorize payment.  If an error occured, show the
	resulting errors
	*/
	$resArray = bank_paypalexpress_hash_call("SetExpressCheckout", $nvpstr);
	$_SESSION['reshash'] = $resArray;

	$ack = strtoupper($resArray["ACK"]);

	if ($ack=="SUCCESS"){
		// Redirect to paypal.com here
		$token = urldecode($resArray["TOKEN"]);
		$payPalURL = parametre_url(_PAYPAL_API_PAYPAL_URL, 'token', $token, '&');

		return $payPalURL;
	}
	else {
		spip_log("Erreur SetExpressCheckout ($id_transaction) :" . var_export($resArray, true), 'paypalexpress' . _LOG_ERREUR);
		return false;
	}
}


function bank_paypalexpress_checkoutpayment($payerid){

	include_spip('inc/date');

	if (!$id_transaction = $_SESSION['id_transaction']){
		spip_log('Erreur id_transaction absent de la session', 'paypalexpress' . _LOG_ERREUR);
		return array(0,false);
	}

	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=" . intval($id_transaction))){
		spip_log("Erreur transaction $id_transaction introuvable", 'paypalexpress' . _LOG_ERREUR);
		return array(0,false);
	}

	// hmm bizare, double hit ? On fait comme si c'etait OK
	if ($row['reglee']=='oui'){
		spip_log("Erreur transaction $id_transaction deja reglee", 'paypalexpress' . _LOG_INFO_IMPORTANTE);
		return array($id_transaction,true);
	}

	// verifier que le payerid est conforme
	if ($payerid!==$_SESSION['payer_id']){
		$trace = "Payerid:$payerid\n".var_export($_SESSION,true);
	 	// sinon enregistrer l'absence de paiement et l'erreur
		include_spip('inc/bank');
		bank_echec_transaction($id_transaction,"paypalexpress",date('Y-m-d H:i:s'),"","Annulee",$trace);
		return array($id_transaction,false);
	}


	/* Gather the information to make the final call to
	finalize the PayPal payment.  The variable nvpstr
	holds the name value pairs
	*/
	$token = urlencode($_SESSION['token']);
	$paymentAmount = $row['montant'];
	$currencyCodeType = "EUR";
	$paymentType = "Sale";
	$payerID = urlencode($_SESSION['payer_id']);
	$serverName = urlencode($_SERVER['SERVER_NAME']);

	$nvpstr = '&TOKEN=' . $token . '&PAYERID=' . $payerID . '&PAYMENTACTION=' . $paymentType . '&AMT=' . $paymentAmount . '&ORDERTOTAL=' . $paymentAmount . '&CURRENCYCODE=' . $currencyCodeType . '&IPADDRESS=' . $serverName;

	/* Make the call to PayPal to finalize payment
	If an error occured, show the resulting errors
	*/
	$resArray = bank_paypalexpress_hash_call("DoExpressCheckoutPayment", $nvpstr);

	$date_paiement = date('Y-m-d H:i:s');

	/* Display the API response back to the browser.
	If the response from PayPal was a success, display the response parameters'
	If the response was an error, display the errors received using APIError.php.
	*/
	$ack = strtoupper($resArray["ACK"]);

	if ($ack!="SUCCESS"){
		spip_log("Erreur DoExpressCheckoutPayment ($id_transaction) :" . var_export($resArray, true), 'paypalexpress' . _LOG_ERREUR);

		$message = "Erreur lors de la transaction avec Paypal. Aucun r&egrave;glement n'a &eacute;t&eacute; r&eacute;alis&eacute;.";
		sql_updateq("spip_transactions",array(
			"reglee"=>'non',
			"statut"=>'echec',
			"message"=>$message,
			"date_paiement"=>$date_paiement),"id_transaction=".intval($id_transaction));
		$_SESSION['reshash'] = $resArray;
		return array($id_transaction,false);
	}

	$authorisation_id = $resArray['TRANSACTIONID'];
	$montant_regle = $resArray['AMT'];
	sql_updateq("spip_transactions",array(
			"autorisation_id"=>$authorisation_id,
			"mode"=>'paypalexpress',
			"montant_regle"=>$montant_regle,
			"date_paiement"=>$date_paiement,
			"statut"=>'ok',
			"reglee"=>'oui'
		),
		"id_transaction=" . intval($id_transaction)
	);
	spip_log("DoExpressCheckoutPayment : id_transaction $id_transaction, reglee", 'paypalexpress' . _LOG_INFO_IMPORTANTE);

	// a faire avant le reglement qui va poser d'autres variables de session
	session_unset();

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));
	return array($id_transaction,true);

}


/**
 * hash_call: Function to perform the API call to PayPal using API signature
 * @methodName is name of API  method.
 * @nvpStr is nvp string.
 * returns an associative array containing the response from the server.
 */
function bank_paypalexpress_hash_call($methodName, $nvpStr){
	$API_UserName = _PAYPAL_API_USERNAME;
	$API_Password = _PAYPAL_API_PASSWORD;
	$API_Signature = _PAYPAL_API_SIGNATURE;
	$API_Endpoint = _PAYPAL_API_ENDPOINT;
	$api_version = _PAYPAL_API_VERSION;

	//NVPRequest for submitting to server
	$nvpreq = "METHOD=" . urlencode($methodName) . "&VERSION=" . urlencode($api_version) . "&PWD=" . urlencode($API_Password) . "&USER=" . urlencode($API_UserName) . "&SIGNATURE=" . urlencode($API_Signature) . $nvpStr;

	//$response = recuperer_page($API_Endpoint,false,false,1048576,$nvpReqArray);

	if (!function_exists('curl_init')){
		include_spip('inc/distant');
		$response = recuperer_page($API_Endpoint . "?$nvpreq");
		$erreur = ($response===false);
		$erreur_msg = "recuperer_page impossible";
	} else {
		//setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		//turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		//if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
		//Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php
		if (_PAYPAL_API_USE_PROXY)
			curl_setopt($ch, CURLOPT_PROXY, _PAYPAL_API_PROXY_HOST . ":" . _PAYPAL_API_PROXY_PORT);

		//setting the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

		//getting response from server
		$response = curl_exec($ch);
		$erreur = curl_errno($ch);
		$erreur_msg = curl_error($ch);
		if (!$erreur){
			//closing the curl
			curl_close($ch);
		}
	}

	//convrting NVPResponse to an Associative Array
	$nvpResArray = bank_paypalexpress_deformatNVP($response);
	//$nvpReqArray=bank_paypalexpress_deformatNVP($nvpreq);
	//$_SESSION['nvpReqArray']=$nvpReqArray;

	if ($erreur){
		// moving to display page to display curl errors
		$_SESSION['curl_error_no'] = $erreur;
		$_SESSION['curl_error_msg'] = $erreur_msg;
		spip_log('Erreur curl : ' . $erreur . ';' . $erreur_msg, 'paypalexpress' . _LOG_ERREUR);
		return false;
	}

	return $nvpResArray;
}

/** This function will take NVPString and convert it to an Associative Array and it will decode the response.
 * It is usefull to search for a particular key and displaying arrays.
 * @nvpstr is NVPString.
 * @nvpArray is Associative Array.
 */
function bank_paypalexpress_deformatNVP($nvpstr){

	$intial = 0;
	$nvpArray = array();
	if (substr($nvpstr, 0, 1)=='&')
		$nvpstr = substr($nvpstr, 1);


	while (strlen($nvpstr)){
		//postion of Key
		$keypos = strpos($nvpstr, '=');
		//position of value
		$valuepos = strpos($nvpstr, '&') ? strpos($nvpstr, '&') : strlen($nvpstr);

		/*getting the Key and Value values and storing in a Associative Array*/
		$keyval = substr($nvpstr, $intial, $keypos);
		$valval = substr($nvpstr, $keypos+1, $valuepos-$keypos-1);
		//decoding the respose
		$nvpArray[urldecode($keyval)] = urldecode($valval);
		$nvpstr = substr($nvpstr, $valuepos+1, strlen($nvpstr));
	}
	return $nvpArray;
}

?>
