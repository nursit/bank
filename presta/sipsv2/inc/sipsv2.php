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

include_spip('inc/bank');

/**
 * Determiner l'URL d'appel serveur en fonction de la config
 *
 * @param array $config
 * @return string
 */
function sipsv2_url_serveur($config){

	$host = "";
	switch($config['service']){
		case "sogenactif":
			if ($config['mode_test']) {
				$host = "https://payment-webinit.simu.sogenactif.com";
			}
			else {
				$host = "https://payment-webinit.sogenactif.com";
			}
			break;
		default:
			$host = "https://payment-webinit.simu.sogenactif.com";
			break;
	}

	return "$host/paymentInit";
}

/**
 * Determiner la cle de signature en fonction de la config
 * @param array $config
 * @return array
 */
function sipsv2_key($config){
	if ($config['mode_test']) {
		return array($config['key_version_test'], $config['secret_key_test']);
	}

	return array($config['key_version'], $config['secret_key']);
}


/**
 * Liste des cartes CB possibles selon la config
 * @param $config
 * @return array
 */
function sipsv2_available_cards($config){

	$mode = $config['presta'];
	$cartes_possibles = array(
		'CB'=>"CB.gif",
		'VISA'=>"VISA.gif",
		'MASTERCARD'=>"MASTERCARD.gif",
		'AMEX' => "AMEX.gif",
	);

	return $cartes_possibles;
}


/**
 * Generer les hidden du form en signant les parametres au prealable
 * @param array $config
 *   configuration du prestataire paiement
 * @param array $parms
 *   parametres du form
 * @return string
 */
function sipsv2_form_hidden($config,$parms){

	list($key_version, $secret_key) = sipsv2_key($config);
	$h = array();
	$data = array();
	foreach($parms as $k=>$v) {
		if (is_array($v)) {
			$v = implode(',', $v);
		}
		$data[] = "$k=$v";
	}
	$data[] = "keyVersion=".$key_version;

	$h['Data'] = implode('|', $data);
	$h['Encode'] = 'base64';
	$h['Data'] = base64_encode($h['Data']);
	$h['InterfaceVersion'] = 'HP_2.16';

	$h = sipsv2_signe_contexte($h, $secret_key);

	$hidden = "";
	foreach($h as $k=>$v){
		$hidden .= "<input type='hidden' name='$k' value='".str_replace("'", "&#39;", $v)."' />";
	}

	return $hidden;
}


/**
 * Signer le contexte en SHA, avec une cle secrete $key
 * @param array $contexte
 * @param string $secret_key
 * @return array
 */
function sipsv2_signe_contexte($contexte, $secret_key) {

	$s = hash('sha256', $contexte['Data'] . $secret_key);
	$contexte['Seal'] = $s;

	return $contexte;
}


/**
 * Verifier la signature de la reponse SIPS
 * @param $values
 * @param $key
 * @return bool
 */
function sipsv2_verifie_signature($values, $key) {
	$seal = (isset($values['Seal'])? $values['Seal'] : null);
	unset($values['Seal']);
	$values = sipsv2_signe_contexte($values, $key);

	if(isset($values['Seal'])
		AND ($values['Seal'] == $seal))	{

		return true;
	}

	return false;
}


/**
 * Recuperer le POST/GET de la reponse dans un tableau
 * en verifiant la signature
 *
 * @param array $config
 * @return array|bool
 */
function sipsv2_recupere_reponse($config){
	$reponse = array();
	foreach($_REQUEST as $k=>$v){
		if (in_array($k, array('Data','Encode','Seal','InterfaceVersion'))){
			$reponse[$k] = $v;
		}
	}

	list($key_version, $secret_key) = sipsv2_key($config);
	$ok = sipsv2_verifie_signature($reponse, $secret_key);

	// si signature invalide
	if (!$ok){
		spip_log("recupere_reponse : signature invalide ".var_export($reponse,true),$config['presta']._LOG_ERREUR);
		return false;
	}

	// ok on peut deserializer le champ Data
	$data = $reponse['Data'];
	if (isset($reponse['Encode'])) {
		if ($reponse['Encode'] == 'base64') {
			$data = base64_decode($data);
		}
		if ($reponse['Encode'] == 'base64url') {
			$data = base64_decode($data); // ?? base64url inconnu
		}
	}

	$data = explode('|', $data);
	$reponse['Data'] = array();

	foreach ($data as $d){
		list($k, $v) = explode('=', $d, 2);
		$reponse['Data'][$k] = $v;
	}

	return $reponse;
}



/**
 * Traiter la reponse apres son decodage
 *
 * @param array $config
 * @param array $response
 * @return array
 */
function sipsv2_traite_reponse_transaction($config, $response) {


/*
  $response :
	array(4) {
		["Data"]=> array(26) {
			["captureDay"]=> string(1) "0"
			["captureMode"]=> string(14) "AUTHOR_CAPTURE"
			["currencyCode"]=> string(3) "978"
			["merchantId"]=> string(15) "002001000000001"
			["orderChannel"]=> string(8) "INTERNET"
			["responseCode"]=> string(2) "00"
			["transactionDateTime"]=> string(25) "2018-01-18T17:57:56+01:00"
			["transactionReference"]=> string(6) "636692"
			["keyVersion"]=> string(1) "1"
			["acquirerResponseCode"]=> string(2) "00"
			["amount"]=> string(3) "600"
			["authorisationId"]=> string(5) "12345"
			["guaranteeIndicator"]=> string(1) "Y"
			["cardCSCResultCode"]=> string(2) "4D"
			["panExpiryDate"]=> string(6) "201802"
			["paymentMeanBrand"]=> string(10) "MASTERCARD"
			["paymentMeanType"]=> string(4) "CARD"
			["customerId"]=> string(2) "13"
			["customerIpAddress"]=> string(14) "83.193.193.137"
			["maskedPan"]=> string(16) "5100##########00"
			["orderId"]=> string(1) "2"
			["holderAuthentRelegation"]=> string(1) "N"
			["holderAuthentStatus"]=> string(10) "3D_SUCCESS"
			["tokenPan"]=> string(16) "g02550747644dd9d"
			["transactionOrigin"]=> string(8) "INTERNET"
			["paymentPattern"]=> string(8) "ONE_SHOT"
		}
		["Seal"]=> string(64) "0365353697e20eacb00bfe4acbd07d4d99024734fb7799b89ba7c9a22c3dad75"
		["InterfaceVersion"]=> string(6) "HP_2.0"
		["Encode"]=> string(6) "base64"
	}
*/

	$mode = $config['presta'];
	$config_id = bank_config_id($config);

	$id_transaction = $response['order_id'];
	$transaction_id = $response['transaction_id'];
	$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction));
	if (!$row){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => sipsv2_shell_args($response)
			)
		);
	}

	/*
	include_spip('inc/filtres');
	if ($transaction_hash!=modulo($row['transaction_hash'],999999)){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode'=>$mode,
				'erreur' => "hash $transaction_hash invalide",
				'log' => sipsv2_shell_args($response)
			)
		);
	}
	*/

	// ok, on traite le reglement
	$date='payment';
	if ($mode == 'sipsabo')
		$date='sub';
	//"Y-m-d H:i:s"
	$date_paiement =
	    substr($response[$date.'_date'],0,4)."-" //annee
	  . substr($response[$date.'_date'],4,2)."-" //mois
	  . substr($response[$date.'_date'],6,2)." " //jour
	  . substr($response[$date.'_time'],0,2).":" //Heures
	  . substr($response[$date.'_time'],2,2).":" //min
	  . substr($response[$date.'_time'],4,2) //sec
	;

	$response_code = sipsv2_response_code($response['response_code']);
	$bank_response_code = sipsv2_bank_response_code($response['bank_response_code']);

	if ($response_code!==true
	 OR $bank_response_code!==true){
	 	// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
	 	if ($row['reglee']=='oui') 
			return array($id_transaction,true);

	 	// sinon enregistrer l'absence de paiement et l'erreur
		return bank_transaction_echec($id_transaction,
			array(
				'mode'=>$mode,
				'config_id' => $config_id,
				'date_paiement' => $date_paiement,
				'code_erreur' => $response['response_code'].(strlen($response['bank_response_code'])?":".$response['bank_response_code']:''),
				'erreur' => trim($response_code." ".$bank_response_code),
				'log' => sipsv2_shell_args($response),
				'send_mail' => $response['response_code']=='03',
			)
		);
	}

	// Ouf, le reglement a ete accepte

	// on verifie que le montant est bon !
	$montant_regle = $response[($mode == 'sipsabo'?'sub_':'').'amount']/100;
	if ($montant_regle!=$row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".sipsv2_shell_args($response),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . '_reglements_partiels');
	}

	// mais sinon on note regle quand meme,
	// pour ne pas creer des problemes hasardeux
	// (il y a des fois une erreur d'un centime)
	$authorisation_id = $response['authorisation_id'];
	$set = array(
		"autorisation_id"=>$authorisation_id,
		"mode"=>"$mode/$config_id",
		"montant_regle"=>$montant_regle,
		"date_paiement"=>$date_paiement,
		"statut"=>'ok',
		"reglee"=>'oui'
	);
	sql_updateq("spip_transactions", $set,"id_transaction=".intval($id_transaction));
	spip_log("call_response : id_transaction $id_transaction, reglee",$mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));
	return array($id_transaction,true);
}

/**
 * Decodage des codes d'erreur
 *
 * @param $code
 * @return bool
 */
function sipsv2_bank_response_code($code){
	if ($code==='00') return true;
	$codes = array(
		0=> "Transaction approuvee ou traitee avec succes",
		2 => "Contacter l'emetteur de carte",
		3 => "Accepteur invalide",
		4 => "Conserver la carte",
		5 => "Ne pas honorer",
		7 => "Conserver la carte, conditions speciales",
		8 => "Approuver apres identification",
		12 => "Transaction invalide",
		13 => "Montant invalide",
		14 => "Numero de porteur invalide",
		15 => "Emetteur de carte inconnu",
		30 => "Erreur de format",
		31 => "Identifiant de l'organisme acquereur inconnu",
		33 => "Date de validite de la carte depassee",
		34 => "Suspicion de fraude",
		41 => "Carte perdue",
		43 => "Carte volee",
		51 => "Provision insuffisante ou credit depasse",
		54 => "Date de validite de la carte depassee",
		56 => "Carte absente du fichier",
		57 => "Transaction non permise a ce porteur",
		58 => "Transaction interdite au terminal",
		59 => "Suspicion de fraude",
		60 => "L'accepteur de carte doit contacter l'acquereur",
		61 => "Depasse la limite du montant de retrait",
		63 => "Regles de securite non respectees",
		68 => "Reponse non parvenue ou recue trop tard",
		90 => "Arret momentane du systeme",
		91 => "Emetteur de cartes inaccessible",
		96 => "Mauvais fonctionnement du systeme",
		97 => "echeance de la temporisation de surveillance globale",
		98 => "Serveur indisponible routage reseau demande a nouveau",
		99 => "Incident domaine initiateur"
	);
	if (strlen($code) AND isset($codes[intval($code)]))
		return $codes[intval($code)];
	return "";
}

/**
 * Dcodage des codes d'erreur (bis)
 * @param $code
 * @return bool
 */
function sipsv2_response_code($code){
	if ($code==='00') return true;
	$codes = array(
		2 =>"demande d'autorisation par telephone a la banque a cause d'un
		depassement de plafond d'autorisation sur la carte (cf. annexe I)",
		3 => "Champ merchant_id invalide, verifier la valeur renseignee dans la requete Contrat de vente a distance inexistant, contacter votre banque.",
		5 => "Autorisation refusee",
		12 => "Transaction invalide, verifier les parametres transferes dans la	requete.",
		17 => "Annulation de l'internaute",
		30 => "Erreur de format.",
		34 => "Suspicion de fraude",
		75 => "Nombre de tentatives de saisie du numero de carte depasse.",
		90 => "Service temporairement indisponible");
	if (isset($codes[intval($code)]))
		return $codes[intval($code)];
	return "";
}

?>