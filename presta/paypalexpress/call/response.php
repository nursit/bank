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

/**
 * Retour de la demande de paiement chez PaypalExpress
 *
 * @return array
 */
function presta_paypalexpress_call_response(){

	include_spip('presta/paypalexpress/inc/paypalexpress');

	/* At this point, the buyer has completed in authorizing payment
	at PayPal.  The script will now call PayPal with the details
	of the authorization, incuding any shipping information of the
	buyer.  Remember, the authorization is not a completed transaction
	at this state - the buyer still needs an additional step to finalize
	the transaction
	*/

	$token = urlencode(_request('token'));
	$id_transaction = $_SESSION['id_transaction'];

	/* Build a second API request to PayPal, using the token as the
	ID to get the details on the payment authorization
	*/
	$nvpstr = "&TOKEN=" . $token;

	/* Make the API call and store the results in an array.  If the
	call was a success, show the authorization details, and provide
	an action to complete the payment.  If failed, show the error
	*/
	$resArray = bank_paypal_hash_call("GetExpressCheckoutDetails", $nvpstr);
	$_SESSION['reshash'] = $resArray;
	$ack = strtoupper($resArray["ACK"]);

	if ($ack=="SUCCESS"){
		$url = $_SESSION['paypalexpress_url_confirm'];
		$url_checkout = generer_action_auteur('paypalexpress_checkoutpayment', '');
		$url = parametre_url($url, 'checkout', $url_checkout, '&');

		$resume = "Paiement par compte Paypal : <br/>" . $resArray['FIRSTNAME'] . ' ' . $resArray['LASTNAME'] . "," . $resArray['EMAIL'];
		$_SESSION['order_resume'] = $resume;
		$_SESSION['token'] = $token;
		$_SESSION['payer_id'] = _request('PayerID');

		include_spip("inc/headers");
		redirige_par_entete($url);
	}
	else {
		spip_log("Erreur GetExpressCheckoutDetails ($id_transaction) :" . var_export($resArray, true), 'paypalexpress' . _LOG_ERREUR);
		return array(0, false);
	}

}
