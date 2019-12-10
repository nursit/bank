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

/**
 * Retour de la demande de paiement chez PaypalExpress
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_paypalexpress_call_response($config, $response = null){

	include_spip('inc/bank');
	$mode = $config['presta'];

	$ack = false;
	include_spip('presta/paypalexpress/inc/paypalexpress');

	/* At this point, the buyer has completed in authorizing payment
	at PayPal.  The script will now call PayPal with the details
	of the authorization, incuding any shipping information of the
	buyer.  Remember, the authorization is not a completed transaction
	at this state - the buyer still needs an additional step to finalize
	the transaction
	*/

	$token = urlencode(_request('token'));
	$id_transaction = intval($_SESSION['id_transaction']);

	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction))){

		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'log' => var_export($_REQUEST, true) . var_export($_SESSION, true),
				'erreur' => 'donnees Paypal non conformes',
			)
		);
	}

	/* Build a second API request to PayPal, using the token as the
	ID to get the details on the payment authorization
	*/
	$nvpstr = "&TOKEN=" . $token;
	#var_dump($nvpstr);

	// pas la peine de faire un call Paypal si Cancel
	if ($token
		AND _request('action')!=='bank_cancel'
		AND !defined('_BANK_CANCEL_TRANSACTION')){
		/* Make the API call and store the results in an array.  If the
		call was a success, show the authorization details, and provide
		an action to complete the payment.  If failed, show the error
		*/
		$resArray = bank_paypalexpress_hash_call($config, "GetExpressCheckoutDetails", $nvpstr);
		#var_dump($resArray);

		$_SESSION['reshash'] = $resArray;
		$ack = strtoupper($resArray["ACK"]);
	}

	if ($ack=="SUCCESS"
		AND isset($resArray["PAYERID"])
		AND isset($resArray["EMAIL"])
		AND $resArray["PAYERID"]==_request('PayerID')){

		$url = $_SESSION['paypalexpress_url_confirm'];
		$url_checkout = generer_action_auteur('paypalexpress_checkoutpayment', $resArray["PAYERID"] . "-" . $mode . "-" . bank_config_id($config));
		$url = parametre_url($url, 'checkout', $url_checkout, '&');

		$resume = "Paiement par compte Paypal : <br/>" . $resArray['FIRSTNAME'] . ' ' . $resArray['LASTNAME'] . "," . $resArray['EMAIL'];
		$_SESSION['order_resume'] = $resume;
		$_SESSION['token'] = $token;
		$_SESSION['payer_id'] = $resArray["PAYERID"];

		// on redirige (un peu sauvagement) sur l'URL de confirmation
		// qui est l'url d'origine du paiement avec un &confirm=oui
		// et va rafficher la commande avec un bouton de validation de paiement
		include_spip("inc/headers");
		redirige_par_entete($url);
	} else {
		// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
		if ($row['reglee']=='oui'){
			return array($id_transaction, true);
		}

		return bank_transaction_echec($id_transaction,
			array(
				'mode' => $mode,
				'config_id' => bank_config_id($config),
				'log' => var_export($_REQUEST, true) . var_export($_SESSION['reshash'], true),
				'erreur' => $ack,
				'where' => 'GetExpressCheckoutDetails'
			)
		);
	}

}