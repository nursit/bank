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

if (!defined('_ECRIRE_INC_VERSION')) return;

session_start();

include_spip('inc/bank');

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

/**
 * Determiner le mode test en fonction d'un define ou de la config
 * @param array $config
 * @return bool
 */
function paypalexpress_is_sandbox($config){
	$test = false;
	// _PAYPAL_API_SANDBOX force a TRUE pour utiliser l'adresse de test de CMCIC
	if ( (defined('_PAYPAL_API_SANDBOX') AND _PAYPAL_API_SANDBOX)
	  OR (isset($config['mode_test']) AND $config['mode_test']) ){
		$test = true;
	}
	return $test;
}


/**
 * Determiner l'URL d'appel serveur en fonction de la config
 * Define the PayPal URL. This is the URL that the buyer is
 * first sent to to authorize payment with their paypal account
 * change the URL depending if you are testing on the sandbox
 * or going to the live PayPal site
 * For the sandbox, the URL is
 * https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=
 * For the live site, the URL is
 * https://www.paypal.com/webscr&cmd=_express-checkout&token=
 *
 * @param array $config
 * @return string
 */
function paypal_api_url($config){

	if (paypalexpress_is_sandbox($config)){
		return 'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout';
	}
	else {
		return 'https://www.paypal.com/webscr&cmd=_express-checkout';
	}
}


/**
 * Endpoint: this is the server URL which you have to connect for submitting your API request.
 *
 * @param array $config
 * @return string
 */
function paypal_api_endpoint($config){

	if (paypalexpress_is_sandbox($config)){
		return 'https://api-3t.sandbox.paypal.com:443/nvp';
	}
	else {
		return 'https://api-3t.paypal.com:443/nvp';
	}
}


/**
 * Initier la demande de paiement Paypal Express Checkout
 * @param $config
 * @param $id_transaction
 * @param null $url_confirm
 * @return bool
 */
function bank_paypalexpress_order_init($config, $id_transaction, $url_confirm=null){
	$mode = $config['presta'];

	if (!$row = sql_fetsel('*', 'spip_transactions', 'id_transaction=' . intval($id_transaction))){
		bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
			)
		);
		return false;
	}

	if ($row['reglee']=='oui'){
		bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction $id_transaction deja reglee",
			)
		);
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

	$returnURL = bank_url_api_retour($config,'response');
	$cancelURL = bank_url_api_retour($config,'cancel');

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
	$resArray = bank_paypalexpress_hash_call($config, "SetExpressCheckout", $nvpstr);
	$_SESSION['reshash'] = $resArray;

	$ack = strtoupper($resArray["ACK"]);

	if ($ack=="SUCCESS"){
		// Redirect to paypal.com here
		$token = urldecode($resArray["TOKEN"]);
		$payPalURL = parametre_url(paypal_api_url($config), 'token', $token, '&');

		return $payPalURL;
	}
	else {
		$erreur = "erreur lors de la demande du jeton";
		if (isset($resArray['L_ERRORCODE0']) AND $resArray['L_ERRORCODE0']=='10002'){
			$erreur .= " - Verifiez les parametres de signature de l'API";
		}

		bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => $erreur,
				'log' => var_export($resArray, true),
				'where' => 'SetExpressCheckout'
			)
		);
		return false;
	}
}


function bank_paypalexpress_checkoutpayment($payerid,$config){

	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']) $mode .= "_test";
	$config_id = bank_config_id($config);


	include_spip('inc/date');

	if (!$id_transaction = $_SESSION['id_transaction']){
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "id_transaction absent de la session",
				'log' => var_export($_SESSION,true)
			)
		);
	}

	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=" . intval($id_transaction))){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => var_export($_SESSION,true)
			)
		);
	}

	// hmm bizare, double hit ? On fait comme si c'etait OK
	if ($row['reglee']=='oui'){
		spip_log("Erreur transaction $id_transaction deja reglee", $mode . _LOG_INFO_IMPORTANTE);
		return array($id_transaction,true);
	}

	// verifier que le payerid est conforme
	if ($payerid!==$_SESSION['payer_id']){
		$trace = "Payerid:$payerid\n".var_export($_SESSION,true);
	 	// sinon enregistrer l'absence de paiement et l'erreur
		return bank_transaction_echec($id_transaction,
			array(
				'mode'=>$mode,
				'config_id' => $config_id,
				'code_erreur' => '',
				'erreur' => "Annulation",
				'log' => $trace,
			)
		);
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
	$resArray = bank_paypalexpress_hash_call($config, "DoExpressCheckoutPayment", $nvpstr);

	$date_paiement = date('Y-m-d H:i:s');

	/* Display the API response back to the browser.
	If the response from PayPal was a success, display the response parameters'
	If the response was an error, display the errors received using APIError.php.
	*/
	$ack = strtoupper($resArray["ACK"]);

	if ($ack!="SUCCESS"){
		$_SESSION['reshash'] = $resArray;
		return bank_transaction_echec($id_transaction,
			array(
				'mode'=>$mode,
				'config_id' => $config_id,
				"date_paiement" => $date_paiement,
				'code_erreur' => '',
				'erreur' => "Erreur lors de la transaction avec Paypal",
				'log' => var_export($resArray, true),
				'where' => 'DoExpressCheckoutPayment',
			)
		);
	}

	$authorisation_id = $resArray['TRANSACTIONID'];
	$montant_regle = $resArray['AMT'];

	$set = array(
		"autorisation_id"=>$authorisation_id,
		"mode"=>"$mode/$config_id",
		"montant_regle"=>$montant_regle,
		"date_paiement"=>$date_paiement,
		"statut"=>'ok',
		"reglee"=>'oui'
	);

	sql_updateq("spip_transactions",$set,
		"id_transaction=" . intval($id_transaction)
	);
	spip_log("DoExpressCheckoutPayment : id_transaction $id_transaction, reglee", $mode . _LOG_INFO_IMPORTANTE);

	if (isset($_SESSION['reshash']) AND $response = $_SESSION['reshash']){
		// si on dispose des informations utilisateurs, les utiliser pour peupler la gloable bank_session
		// qui peut etre utilisee pour creer le compte client a la volee
		$var_users = array(
			'EMAIL' => 'email',
			'LASTNAME' => 'nom',
			'FIRSTNAME' => 'prenom',
			'SHIPTONAME'=>'nom',
			'SHIPTOSTREET'=>'adresse',
			'SHIPTOCITY'=>'ville',
			'SHIPTOZIP'=>'code_postal',
			'SHIPTOCOUNTRYCODE'=>'pays'
		);
		foreach ($var_users as $kr => $ks){
			if (isset($response[$kr]) AND $response[$kr]){
				if (!isset($GLOBALS['bank_session'])) $GLOBALS['bank_session'] = array();
				$GLOBALS['bank_session'][$ks] = $response[$kr];
			}
		}
	}

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
 *
 * @param $config
 *   configuration du module
 * @param $methodName
 * @param $nvpStr
 * @return array|bool
 */
function bank_paypalexpress_hash_call($config, $methodName, $nvpStr){
	$API_UserName = $config['API_USERNAME'];
	$API_Password = $config['API_PASSWORD'];
	$API_Signature = $config['API_SIGNATURE'];
	$API_Endpoint = paypal_api_endpoint($config);
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
		#curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

		//turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		if (!defined('CURL_SSLVERSION_TLSv1_2')) {
			define('CURL_SSLVERSION_TLSv1_2', 6);
		}
		curl_setopt ($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		//if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
		//Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php
		if (_PAYPAL_API_USE_PROXY)
			curl_setopt($ch, CURLOPT_PROXY, _PAYPAL_API_PROXY_HOST . ":" . _PAYPAL_API_PROXY_PORT);

		//setting the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close', 'User-Agent: SPIP/Bank'));

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
		spip_log('Erreur curl : ' . $erreur . ';' . $erreur_msg, $config['presta'] . _LOG_ERREUR);
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
