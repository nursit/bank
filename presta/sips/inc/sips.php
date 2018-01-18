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
 * Ecrire les fichiers de config/parametres a la volee avant l'appel a un binaire SIPS
 * @param string $service
 * @param string $merchant_id
 * @param string $certificat
 * @param string $dir_logo
 * @return string
 */
function sips_ecrire_config_merchant($service,$merchant_id,$certificat,$dir_logo){
	// creer les fichiers config pour la transaction
	$pathfile = sous_repertoire(_DIR_TMP,"sips");
	$pathfile = sous_repertoire($pathfile,$service);
	$realdir = realpath($pathfile);
	$config_file =
	"DEBUG!NO!\n"
	. "D_LOGO!".substr($dir_logo,strlen(_DIR_RACINE))."!\n"
	. "F_DEFAULT!$realdir/parmcom.sips!\n"
	. "F_PARAM!$realdir/parmcom!\n"
	. "F_CERTIFICATE!$realdir/certif!";

	ecrire_fichier($p=$pathfile."pathfile",$config_file);

	// le fichier par defaut
	if (!file_exists($pathfile."parmcom.sips"))
		copy(_DIR_PLUGIN_BANK."presta/sips/bin/$service/param/parmcom.sips",$pathfile."parmcom.sips");
	// le fichier du merchant
	if (!file_exists($pathfile."parmcom.$merchant_id"))
		copy(_DIR_PLUGIN_BANK."presta/sips/bin/$service/param/parmcom",$pathfile."parmcom.$merchant_id");
	// le certificat
	if ($merchant_id)
		ecrire_fichier($p=$pathfile."certif.fr.$merchant_id",$certificat);

	return $realdir;
}

/**
 * Echapper les arguments en ligne de commande
 * @param $params
 * @return string
 */
function sips_shell_args($params){
	$res = "";
	foreach($params as $k=>$v){
		if (preg_match(',[^A-Z0-9],i',$v))
			$v="'".addslashes($v)."'";
		else
			$v = escapeshellcmd($v);
		$res .= " ".escapeshellcmd($k)."=".$v;
	}
	return $res;
}

/**
 * Generer la requete d'appel
 *
 * @param $service
 * @param $params
 * @param $certificat
 * @param string $request
 * @return array
 */
function sips_request($service,$params,$certificat,$request = "request"){
	// enlever les header moches
	$params['header_flag'] = 'no';

	$dir_logo = find_in_path("presta/sips/logo/"); // permettre la surcharge des images
	$sips_exec_request = charger_fonction("exec_request","presta/sips");
	$result = $sips_exec_request($service,$params,$certificat,$dir_logo,$request);

	//	sortie de la fonction : $result=!code!error!buffer!
	//	    - code=0	: la fonction genere une page html contenue dans la variable buffer
	//	    - code=-1 	: La fonction retourne un message d'erreur dans la variable error

	//On separe les differents champs et on les met dans une variable tableau
	$result = explode ("!", $result);
	// supprimer les align="center" desuet
	$result[3] = preg_replace(',align=([\'"]?)center(\\1),Uims','',$result[3]);

	$result['code'] = $result[1];
	$result['error'] = $result[2];
	$result['buffer'] = $result[3];

	if (( $result['code'] == "" ) && ( $result['error'] == "" ) ) {
 		$result['buffer'] = "<p>erreur appel $request</p>executable $request non trouve";
 		spip_log("erreur appel $request : executable $request non trouve",'sips');
	}

	if (preg_match_all(",<form\b[^>]*>,UimsS",$result['buffer'], $regs, PREG_PATTERN_ORDER)){
		foreach($regs as $reg){
			$class = extraire_attribut($reg[0],"class");
			$class .= ($class?" ":"") . "noajax";
			$form = inserer_attribut($reg[0],"class",$class);
			$result['buffer'] = str_replace($reg[0],$form,$result['buffer']);
		}
	}

	return $result;
}

/**
 * Decoder la reponse de retour
 *
 * @param $service
 * @param $merchant_id
 * @param $certificat
 * @param string $response
 * @return array
 */
function sips_response($service, $merchant_id, $certificat, $response = 'response'){

	$params = array('message'=>_request('DATA'));
	$params['merchant_id'] = $merchant_id;

	$dir_logo = find_in_path("presta/sips/logo/"); // permettre la surcharge des images
	$sips_exec_response = charger_fonction("exec_response","presta/sips");
	$result = $sips_exec_response($service,$params,$certificat,$dir_logo,$response);

	//	Sortie de la fonction : !code!error!v1!v2!v3!...!v29
	//		- code=0	: la fonction retourne les donnees de la transaction dans les variables v1, v2, ...
	//				: Ces variables sont decrites dans le GUIDE DU PROGRAMMEUR
	//		- code=-1 	: La fonction retourne un message d'erreur dans la variable error

	//	on separe les differents champs et on les met dans une variable tableau
	$result = explode ("!", $result);

	if ($response=='response') {
		//	Recuperation des donnees de la reponse
		$result['code'] = $result[1];
		$result['error'] = $result[2];
		$result['merchant_id'] = $result[3];
		$result['merchant_country'] = $result[4];
		$result['amount'] = $result[5];
		$result['transaction_id'] = $result[6];
		$result['payment_means'] = $result[7];
		$result['transmission_date'] = $result[8];
		$result['payment_time'] = $result[9];
		$result['payment_date'] = $result[10];
		$result['response_code'] = $result[11];
		$result['payment_certificate'] = $result[12];
		$result['authorisation_id'] = $result[13];
		$result['currency_code'] = $result[14];
		$result['card_number'] = $result[15];
		$result['cvv_flag'] = $result[16];
		$result['cvv_response_code'] = $result[17];
		$result['bank_response_code'] = $result[18];
		$result['complementary_code'] = $result[19];
		$result['complementary_info'] = $result[20];
		$result['return_context'] = $result[21];
		$result['caddie'] = $result[22];
		$result['receipt_complement'] = $result[23];
		$result['merchant_language'] = $result[24];
		$result['language'] = $result[25];
		$result['customer_id'] = $result[26];
		$result['order_id'] = $result[27];
		$result['customer_email'] = $result[28];
		$result['customer_ip_address'] = $result[29];
		$result['capture_day'] = $result[30];
		$result['capture_mode'] = $result[31];
		$result['data'] = $result[32];
	}
	elseif ($response=='responseabo'){
		$result['code'] = $result[1];
		$result['error'] = $result[2];
		$result['merchant_id'] = $result[3];
		$result['transaction_id'] = $result[4];
		$result['transmission_date'] = $result[5];
		$result['sub_time'] = $result[6];
		$result['sub_date'] = $result[7];
		$result['response_code'] = $result[8];
		$result['bank_response_code'] = $result[9];
		$result['cvv_response_code'] = $result[10];
		$result['cvv_flag'] = $result[11];
		$result['complementary_code'] = $result[12];
		$result['complementary_info'] = $result[13];
		$result['sub_payment_mean'] = $result[14];
		$result['card_number'] = $result[15];
		$result['card_validity'] = $result[16];
		$result['payment_certificate'] = $result[17];
		$result['authorisation_id'] = $result[18];
		$result['currency_code'] = $result[19];
		$result['sub_type'] = $result[20];
		$result['sub_amount'] = $result[21];
		$result['capture_day'] = $result[22];
		$result['capture_mode'] = $result[23];
		$result['merchant_language'] = $result[24];
		$result['merchant_country'] = $result[25];
		$result['language'] = $result[26];
		$result['receipt_complement'] = $result[27];
		$result['caddie'] = $result[28];
		$result['data'] = $result[29];
		$result['return_context'] = $result[30];
		$result['customer_ip_address'] = $result[31];
		$result['order_id'] = $result[32];
		$result['sub_operation_code'] = $result[33];

		$result['sub_subscriber_id'] = $result[34];
		$result['sub_civil_status'] = $result[35];
		$result['sub_lastname'] = $result[36];
		$result['sub_firstname'] = $result[37];
		$result['sub_address1'] = $result[38];
		$result['sub_address2'] = $result[39];
		$result['sub_zipcode'] = $result[40];
		$result['sub_city'] = $result[41];
		$result['sub_country'] = $result[42];
		$result['sub_telephone'] = $result[43];
		$result['sub_email'] = $result[44];
		$result['sub_description'] = $result[45];
	}
	return $result;
}

/**
 * Traiter la reponse apres son decodage
 *
 * @param array $config
 * @param array $response
 * @return array
 */
function sips_traite_reponse_transaction($config, $response) {

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
				'log' => sips_shell_args($response)
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
				'log' => sips_shell_args($response)
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

	$response_code = sips_response_code($response['response_code']);
	$bank_response_code = sips_bank_response_code($response['bank_response_code']);

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
				'log' => sips_shell_args($response),
				'send_mail' => $response['response_code']=='03',
			)
		);
	}

	// Ouf, le reglement a ete accepte

	// on verifie que le montant est bon !
	$montant_regle = $response[($mode == 'sipsabo'?'sub_':'').'amount']/100;
	if ($montant_regle!=$row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".sips_shell_args($response),$mode);
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
function sips_bank_response_code($code){
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
function sips_response_code($code){
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