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
 * @param array $config
 * @param array|null $response
 *     Réponse déjà obtenue de la banque si transmis. Sinon sera calculé.
 * @return array(int $id_transaction, bool $paiement_ok)
**/
function presta_cmcic_call_response_dist($config, $response=null){

	include_spip('inc/bank');

	// Cas B : retour depuis la banque (annulation avant paiement ou fin de la procédure)
	if ($res = cmcic_terminer_transaction()) {
		return $res;
	}

	// Cas A : appel de la banque attendant traitements et notification
	if (!$response) {
		$response = cmcic_response($config);
	}

	if (!$response) {
		cmcic_notifier_banque_erreur();
		cmcic_notifier_banque_erreur();
		return array(0, false);
	}

	return cmcic_traite_reponse_transaction($config, $response);
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
		include_spip('inc/bank');
		return bank_transaction_invalide($id_transaction,
			array(
				'mode'=>"cmcic",
				'erreur' => "Id=$id mal forme dans l'URL",
				'send_mail' => false,
			)
		);
	}

	// transaction existante ?
	$row = sql_fetsel("id_transaction, reglee","spip_transactions",
		"id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash));
	if (!$row) {
		include_spip('inc/bank');
		return bank_transaction_invalide($id_transaction,
			array(
				'mode'=>"cmcic",
				'erreur' => "Erreur avec $id reçu de l'url : transaction introuvable",
				'send_mail' => false,
			)
		);
	}

	// la transaction est là.
	spip_log("call_response : $id reçu de l'url OK. Payée : ".$row['reglee'], "cmcic");
	return array($id_transaction, $row['reglee'] == 'oui');

}

/**
 * Traite la réponse de la banque
 *
 * @param array $config
 *     configuration du module
 * @param array $response
 *     Données envoyées par la banque
 * @return array(int $id_transaction, bool $paiement_ok)
**/
function cmcic_traite_reponse_transaction($config, $response) {
	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']) $mode .= "_test";
	$config_id = bank_config_id($config);

	#spip_log("call_response : traitement d'une réponse de la banque $mode !", $mode);
	
	// on verifie que notre transaction existe bien
	$contenu = $response['texte-libre'];
	$contenu = urldecode($contenu);
	$contenu = @unserialize($contenu);
	if ($contenu===false) {
		$res = bank_transaction_invalide(0,
			array(
				'mode'=>$mode,
				'erreur' => "contenu non deserialisable",
				'log' => var_export($response,true)
			)
		);
		cmcic_notifier_banque_erreur();
		return $res;
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
		$res = bank_transaction_invalide($id_transaction,
			array(
				'mode'=>$mode,
				'erreur' => "$id_transaction / $transaction_hash inconnu",
				'log' => var_export($response,true)
			)
		);
		cmcic_notifier_banque_erreur();
		return $res;
	}

	// ici on a tout bon !
	#spip_log("call_response : données de la banque correctes. On les traite.", $mode);

	switch($response['code-retour']) {
		case "Annulation" :
			// Payment has been refused
			// put your code here (email sending / Database update)
			// Attention : an autorization may still be delivered for this payment
			$retour = cmcic_gerer_transaction_annulee($config, $id_transaction, $response, $row);
			break;

		case "payetest":
			// Payment has been accepeted on the test server
			// put your code here (email sending / Database update)
			$retour = cmcic_gerer_transaction_payee($config, $id_transaction, $response, $row, true);
			break;

		case "paiement":
			// Payment has been accepted on the productive server
			// put your code here (email sending / Database update)
			$retour = cmcic_gerer_transaction_payee($config, $id_transaction, $response, $row);
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
	printf(_MONETICOPAIEMENT_PHASE2BACK_RECEIPT, _MONETICOPAIEMENT_PHASE2BACK_MACOK);
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
	printf(_MONETICOPAIEMENT_PHASE2BACK_RECEIPT, _MONETICOPAIEMENT_PHASE2BACK_MACNOTOK);
	spip_log("call_response : notification pour banque : ERREUR.", "cmcic");
}


/**
 * Retrouve la réponse de la banque CIC et vérifie sa sécurité
 * 
 * @param array $config
 * @return bool|array
 *     False si erreur ou clé de sécurité erronnée
 *     array : tableau des données de la banque sinon
**/
function cmcic_response($config) {
	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']) $mode .= "_test";

	// Begin Main : Retrieve Variables posted by CMCIC Payment Server 
	$MoneticoPaiement_bruteVars = getMethode();
	spip_log("call_response : réception des variables cmcic", $mode);

	// peu de chance d'être en erreur ici, mais sait-on jamais
	if (!$MoneticoPaiement_bruteVars) {
		spip_log("call_response : variables cmcic introuvables", $mode);
		#return presta_cmcic_notifier_banque_erreur();
		return false;
	}

	// TPE init variables
	$oTpe  = new MoneticoPaiement_Ept($config);
	$oHmac = new MoneticoPaiement_Hmac($oTpe);

	// Message Authentication
	$MAC_source = cmcic_concat_response_fields($MoneticoPaiement_bruteVars, $oTpe);
	$computed_MAC = $oHmac->computeHmac($MAC_source);

	// uniquement si le code de sécurité correspond
	if (!array_key_exists('MAC', $MoneticoPaiement_bruteVars)
	  or strtolower($MoneticoPaiement_bruteVars['MAC']) !== $computed_MAC) {
		spip_log("call_response : clé de sécurité falsifiée ou erronée", $mode);
		return false;
		#return presta_cmcic_notifier_banque_erreur();
	}

	// clé correcte
	return $MoneticoPaiement_bruteVars;
}


/**
 * Traiter l'annulation d'une transaction
 *
 * @param array $config
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
function cmcic_gerer_transaction_annulee($config, $id_transaction, $response, $row, $erreur=true) {
	$mode = $config['presta'];
	$config_id = bank_config_id($config);
	if (isset($config['mode_test']) AND $config['mode_test']) $mode .= "_test";

	// regarder si l'annulation n'arrive pas apres un reglement
	// (internaute qui a ouvert 2 fenetres de paiement)
	if ($row['reglee']!='oui') {
		$date_paiement = date('Y-m-d H:i:s');
		include_spip('inc/bank');
		return bank_transaction_echec($id_transaction,
			array(
				'mode'=>$mode,
				'config_id'=>$config_id,
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
 * @param array $config
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
function cmcic_gerer_transaction_payee($config, $id_transaction, $response, $row, $paiement_test = false) {
	$mode = $config['presta'];
	$config_id = bank_config_id($config);
	if ($paiement_test) $mode .= "_test";

	// ok, on traite le reglement
	$now=time();
	$date_paiement = date("Y-m-d H:i:s",$now);

	// recuperer la date de paiement si possible
	if (isset($response['date'])) {
		// 24/05/2019:10:00:25
		$d = explode(':', $response['date']);
		list($j, $m, $a) = explode('/', $d[0]);
		if ($t = mktime($d[1], $d[2], $d[3], $m, $j, $a)) {
			$date_paiement = date("Y-m-d H:i:s", $t);
		}
	}

	// on verifie que le montant est bon !
	$montant_regle = floatval( substr($response['montant'], 0, -3) ); // enlever la devise
	if ($montant_regle!=$row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".var_export($response,true),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . '_reglements_partiels');

		// mais on continue en acceptant quand meme le paiement
		// car l'erreur est en general dans le traitement
	}

	// si on a pas mieux
	$autorisation_id = $response['numauto'];   # numéro d'autorisation de la banque
	$transaction     = $response['reference']; # référence de transaction
	if (isset($response['authentification'])
	  and $authentification_base64 = $response['authentification']
		and $authentification_json = base64_decode($authentification_base64)
		and $authentification = json_decode($authentification_json, true)) {

		if (isset($authentification['details']['transactionID']) and $authentification['details']['transactionID']) {
			$transaction = $authentification['details']['transactionID'];
		}
	}

	$set = array(
		"autorisation_id"=>"$autorisation_id/$transaction",
		"mode"=>"$mode/$config_id",
		"montant_regle"=>$montant_regle,
		"date_paiement"=>$date_paiement,
		"statut"=>'ok',
		"reglee"=>'oui'
	);

	// si on a les infos de validite / card number, on les note ici
	if (isset($response['vld'])){
		$set['validite'] = "20".substr($response['vld'],2) . "-" . substr($response['vld'],0,2);
	}
	if (isset($response['brand']) OR isset($response['cbmasquee'])){
		// par defaut on note brand et number dans refcb
		// qui peut etre reutilise
		$set['refcb'] = '';
		if (isset($response['brand']) && $response['brand'] != 'na'){
			$set['refcb'] = $response['brand'];
		}
		if (isset($response['cbmasquee']))
			$set['refcb'] .= " ".$response['cbmasquee'];
		$set['refcb'] = trim($set['refcb']);
		if (!$set['refcb']) {
			unset($set['refcb']);
		}
	}

	sql_updateq("spip_transactions", $set, "id_transaction=".intval($id_transaction));
	spip_log("call_response : id_transaction $id_transaction, reglee", $mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));
	return array($id_transaction, true);
}

