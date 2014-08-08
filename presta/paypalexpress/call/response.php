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
	$mode = "paypalexpress";

	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction))){
		spip_log($t = "call_response : id_transaction $id_transaction inconnu:".var_export($_REQUEST,true),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . "_douteux");
		// on mail le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[$mode]Transaction Frauduleuse",$t,"$mode@".$_SERVER['HTTP_HOST']);
		$message = "Une erreur est survenue, les donn&eacute;es re&ccedil;ues de Paypal ne sont pas conformes. ";
		$message .= "Votre r&egrave;glement n'a pas &eacute;t&eacute; pris en compte (Ref : $id_transaction)";
		$set = array(
			"mode" => $mode,
			"message"=>$message,
			'statut'=>'echec'
		);
		sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));
		return array($id_transaction,false);
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
		$resArray = bank_paypalexpress_hash_call("GetExpressCheckoutDetails", $nvpstr);
		#var_dump($resArray);

		$_SESSION['reshash'] = $resArray;
		$ack = strtoupper($resArray["ACK"]);
	}

	if ($ack=="SUCCESS"
		AND isset($resArray["PAYERID"])
		AND isset($resArray["EMAIL"])
	  AND $resArray["PAYERID"]==_request('PayerID')){

		$url = $_SESSION['paypalexpress_url_confirm'];
		$url_checkout = generer_action_auteur('paypalexpress_checkoutpayment', $resArray["PAYERID"]);
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
	}
	else {
	 	// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
	 	if ($row['reglee']=='oui') return array($id_transaction,true);

	 	// sinon enregistrer l'absence de paiement et l'erreur
		$trace = var_export($_REQUEST,true);
		if (isset($_SESSION['reshash']))
			$trace .= var_export($_SESSION['reshash'],true);
		spip_log($t="Erreur GetExpressCheckoutDetails ($id_transaction) : transaction $id_transaction annulee :".$trace,$mode . _LOG_ERREUR);
		$set = array(
			"mode" => $mode,
			"statut" => 'echec',
			'date_paiement' => date('Y-m-d H:i:s')
		);
		sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));

		$message = "Aucun r&egrave;glement n'a &eacute;t&eacute; r&eacute;alis&eacute;";
		sql_updateq("spip_transactions",array("message"=>$message),"id_transaction=".intval($id_transaction));
		return array($id_transaction,false);
	}

}