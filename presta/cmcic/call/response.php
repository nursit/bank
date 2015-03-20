<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('presta/cmcic/inc/cmcic');
include_spip('inc/date');


/**
 * Gestion de la réponse de la banque
 * 
 * Il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * On arrive ici à 2 monents :
 *
 * A) via l'URL CGI2 à donner à sa banque (?action=bank_autoresponse&bankp=cmcic)
 * qui doit traiter les données ET retourner une notification à la banque !
 * Dans les données reçues, le texte-libre possède alors notre id_transaction
 * et notre hash correspondant (l'URL n'en a pas connaissance)
 *
 * B) via les URLs de retour (ok ou erreur) cliquées pour retourner à notre
 * site après le paiement effectif ou annuler la transaction, cela depuis
 * le site de la banque. Les urls de retour ont, elles, les id & hash de
 * la transaction.
 * 
 * Dans le cas A, la fonction doit retourner un texte de notification
 * pour la banque et donc, ne pas rediriger ensuite sur une url du site.
 * Pour cela, l'URL utilise l'action «bank_autoresponse».
 *
 * Dans le cas B, la fonction doit juste rediriger sans effectuer de
 * traitements supplémentaires (sauf en cas d'annulation avant le paiement)
 * 
 * @param array|null $response
 *     Réponse déjà obtenue de la banque si transmis. Sinon sera calculé.
 * @return array(int $id_transaction, bool $paiement_ok)
**/
function presta_cmcic_call_response_dist($response=null){
	$mode = 'cmcic';

	// Cas B : retour depuis la banque (annulation avant paiement ou fin de la procédure)
	if ($res = cmcic_terminer_transaction()) {
		return $res;
	}

	// Cas A : appel de la banque attendant traitements et notification
	if (!$response) {
		$response = cmcic_response();
	}

	if (!$response) {
		cmcic_notifier_banque_erreur();
		return array(0, false);
	}

	return cmcic_traite_reponse_transaction($response);
}


/**
 * Teste si l'URL dispose des id et hash de transaction,
 * auquel cas, on est dans une fin de procédure avec la banque
 *
 * @return false|array
 *     false si ce n'est pas la fin
 *     array(int id_transaction, bool paiement_ok) sinon.
**/
function cmcic_terminer_transaction() {
	// id contient id;hash si retour, sinon rien pour le traitement
	if (!$id = _request('id')) {
		return false;
	}

	// dans ce cas c'est un retour. En espérant qu'il n'est pas foireux
	list($id_transaction, $transaction_hash) = explode(";", $id);

	// pas vide ?
	if (!$id_transaction OR !$transaction_hash) {
		spip_log("call_response : Incohérence avec $id reçu de l'url", "cmcic");
		return array(0, false);
	}

	// transaction existante ?
	$row = sql_fetsel("id_transaction, reglee","spip_transactions",
		"id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash));
	if (!$row) {
		spip_log("call_response : Erreur avec $id reçu de l'url : transaction introuvable", "cmcic");
		return array(0, false);
	}

	// la transaction est là.
	spip_log("call_response : $id reçu de l'url OK. Payée : $row[reglee].", "cmcic");
	return array($id_transaction, $row['reglee'] == 'oui');

}

/**
 * Traite la réponse de la banque
 *
 * @param array $response
 *     Données envoyées par la banque
 * @param string $mode
 *     Type de banque
 * @return array(int $id_transaction, bool $paiement_ok)
**/
function cmcic_traite_reponse_transaction($response, $mode = "cmcic") {

	spip_log("call_response : traitement d'une réponse de la banque $mode !", $mode);
	
	// on verifie que notre transaction existe bien
	$contenu = $response['texte-libre'];
	$contenu = urldecode($contenu);
	$contenu = @unserialize($contenu);
	if ($contenu===false) {
		spip_log("call_response : contenu non désérialisable !", $mode);
		cmcic_notifier_banque_erreur();
		return array(0, false);
	}

	// id & hash
	$id_transaction   = $contenu['id'];
	$transaction_hash = $contenu['hash'];
	$lang             = $contenu['lang'];

	// remettre la langue de l'utilisateur qui a demandé la transaction
	// puisque ici c'est le serveur cmcic qui fait le hit
	include_spip('inc/lang');
	changer_langue($lang);


	// cette ligne id/hash doit exister !
	if (!$row = sql_fetsel("*","spip_transactions",array(
		"id_transaction=".intval($id_transaction),
		'transaction_hash='.sql_quote($contenu['hash']))))
	{
		spip_log($t = "call_response : id_transaction $id_transaction / $transaction_hash inconnu:", $mode);
		// on log ca dans un journal dedie
		spip_log($t, $mode . "_douteux");
		// on mail le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[$mode]Transaction Frauduleuse",$t,"$mode@".$_SERVER['HTTP_HOST']);
		#$message = "Une erreur est survenue, les donn&eacute;es re&ccedil;ues de la banque ne sont pas conformes. ";
		#$message .= "Votre r&egrave;glement n'a pas &eacute;t&eacute; pris en compte (Ref : $id_transaction)";
		#sql_updateq("spip_transactions",array("message"=>$message,'statut'=>'echec'),"id_transaction=".intval($id_transaction));
		#return array($id_transaction,false);
		cmcic_notifier_banque_erreur();
		return array(0, false);
	}

	// ici on a tout bon !
	spip_log("call_response : données de la banque correctes. On les traite.", $mode);

	switch($response['code-retour']) {
		case "Annulation" :
			// Payment has been refused
			// put your code here (email sending / Database update)
			// Attention : an autorization may still be delivered for this payment
			$retour = cmcic_gerer_transaction_annulee($id_transaction, $response, $row);
			break;

		case "payetest":
			// Payment has been accepeted on the test server
			// put your code here (email sending / Database update)
			$retour = cmcic_gerer_transaction_payee($id_transaction, $response, $row, true);
			break;

		case "paiement":
			// Payment has been accepted on the productive server
			// put your code here (email sending / Database update)
			$retour = cmcic_gerer_transaction_payee($id_transaction, $response, $row);
			break;


		/*** ONLY FOR MULTIPART PAYMENT ***/
		case "paiement_pf2":
		case "paiement_pf3":
		case "paiement_pf4":
			// Payment has been accepted on the productive server for the part #N
			// return code is like paiement_pf[#N]
			// put your code here (email sending / Database update)
			// You have the amount of the payment part in $CMCIC_bruteVars['montantech']
			break;

		case "Annulation_pf2":
		case "Annulation_pf3":
		case "Annulation_pf4":
			// Payment has been refused on the productive server for the part #N
			// return code is like Annulation_pf[#N]
			// put your code here (email sending / Database update)
			// You have the amount of the payment part in $CMCIC_bruteVars['montantech']
			break;
			
	}

	cmcic_notifier_banque_ok();
	return $retour;
}


/**
 * Notifie à la banque un retour cohérent
 *
 * @return void
**/
function cmcic_notifier_banque_ok() {
	// Send receipt to CMCIC server
	header("Pragma: no-cache");
	header("Content-type: text/plain");
	printf(_CMCIC_CGI2_RECEIPT, _CMCIC_CGI2_MACOK);
	spip_log("call_response : notification pour banque : OK.", "cmcic");
}


/**
 * Notifie à la banque un retour incohérent
 *
 * @return void
**/
function cmcic_notifier_banque_erreur() {
	// Send receipt to CMCIC server
	header("Pragma: no-cache");
	header("Content-type: text/plain");
	printf(_CMCIC_CGI2_RECEIPT, _CMCIC_CGI2_MACNOTOK);
	spip_log("call_response : notification pour banque : ERREUR.", "cmcic");
}


/**
 * Retrouve la réponse de la banque CIC et vérifie sa sécurité
 * 
 * @return bool|array
 *     False si erreur ou clé de sécurité erronnée
 *     array : tableau des données de la banque sinon
**/ 
function cmcic_response() {
	$mode = "cmcic";

	// Begin Main : Retrieve Variables posted by CMCIC Payment Server 
	$CMCIC_bruteVars = getMethode();
	spip_log("call_response : réception des variables cmcic", $mode);

	// peu de chance d'être en erreur ici, mais sait-on jamais
	if (!$CMCIC_bruteVars) {
		spip_log("call_response : variables cmcic introuvables", $mode);
		#return presta_cmcic_notifier_banque_erreur();
		return false;
	}

	// TPE init variables
	$oTpe  = new CMCIC_Tpe();
	$oHmac = new CMCIC_Hmac($oTpe);

	// Message Authentication
	$cgi2_fields = sprintf(_CMCIC_CGI2_FIELDS, $oTpe->sNumero,
		$CMCIC_bruteVars["date"],
		$CMCIC_bruteVars['montant'],
		$CMCIC_bruteVars['reference'],
		$CMCIC_bruteVars['texte-libre'],
		$oTpe->sVersion,
		$CMCIC_bruteVars['code-retour'],
		$CMCIC_bruteVars['cvx'],
		$CMCIC_bruteVars['vld'],
		$CMCIC_bruteVars['brand'],
		$CMCIC_bruteVars['status3ds'],
		$CMCIC_bruteVars['numauto'],
		$CMCIC_bruteVars['motifrefus'],
		$CMCIC_bruteVars['originecb'],
		$CMCIC_bruteVars['bincb'],
		$CMCIC_bruteVars['hpancb'],
		$CMCIC_bruteVars['ipclient'],
		$CMCIC_bruteVars['originetr'],
		$CMCIC_bruteVars['veres'],
		$CMCIC_bruteVars['pares']
		);


	// uniquement si le code de sécurité correspond
	if ($oHmac->computeHmac($cgi2_fields) != strtolower($CMCIC_bruteVars['MAC']))
	{
		spip_log("call_response : clé de sécurité falsifiée ou erronée", $mode);
		return false;
		#return presta_cmcic_notifier_banque_erreur();
	}

	// clé correcte
	return $CMCIC_bruteVars;
}


/**
 * Traiter l'annulation d'une transaction
 *
 * @param int $id_transaction
 *     Identification de la transaction
 * @param array $response
 *     Réponse de la banque
 * @param array $row
 *     Ligne de transaction
 * @param bool|string $erreur
 *    Message d'erreur eventuel
 * @return array
**/ 
function cmcic_gerer_transaction_annulee($id_transaction, $response, $row, $erreur=true) {
	$mode = "cmcic";

	// regarder si l'annulation n'arrive pas apres un reglement
	// (internaute qui a ouvert 2 fenetres de paiement)
	if ($row['reglee']!='oui') {
		$date_paiement = date('Y-m-d H:i:s');
		include_spip('inc/bank');
		return bank_transaction_echec($id_transaction,
			array(
				'mode'=>$mode,
				'date_paiement' => $date_paiement,
				'code_erreur' => $response['motifrefus'],
				'erreur' => $erreur===true?"":$erreur,
				'log' => bank_shell_args($response)
			)
		);
	}

	return array($id_transaction, true);
}

/**
 * Traiter le paiement d'une transaction
 *
 * @param int $id_transaction
 *     Identification de la transaction
 * @param array $response
 *     Réponse de la banque
 * @param array $row
 *     Ligne de transaction
 * @param bool $paiement_test
 *     Est-ce un paiement via le serveur de test ?
 * @return array
**/ 
function cmcic_gerer_transaction_payee($id_transaction, $response, $row, $paiement_test = false) {
	$mode = "cmcic";
	if ($paiement_test) $mode = "cmcic_test";

	// ok, on traite le reglement
	$date=time();
	$date_paiement = date("Y-m-d H:i:s",$date);

	// on verifie que le montant est bon !
	$montant_regle = floatval( substr($response['montant'], 0, -3) ); // enlever la devise
	if ($montant_regle!=$row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".var_export($response,true),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . '_reglements_partiels');

		// mais on continue en acceptant quand meme le paiement
		// car l'erreur est en general dans le traitement
	}


	$autorisation_id = $response['numauto'];   # numéro d'autorisation de la banque
	$transaction     = $response['reference']; # référence de transaction

	$set = array(
		"autorisation_id"=>"$transaction/$autorisation_id",
		"mode"=>$mode,
		"montant_regle"=>$montant_regle,
		"date_paiement"=>$date_paiement,
		"statut"=>'ok',
		"reglee"=>'oui'
	);
	sql_updateq("spip_transactions", $set, "id_transaction=".intval($id_transaction));
	spip_log("call_response : id_transaction $id_transaction, reglee", $mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,"",$row);
	return array($id_transaction, true);
}
?>
