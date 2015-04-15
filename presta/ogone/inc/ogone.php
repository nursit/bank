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

include_spip('inc/bank');


/**
 * Determiner le mode test en fonction d'un define ou de la config
 * @param array $config
 * @return bool
 */
function ogone_is_sandbox($config){
	$test = false;
	// _CMCIC_TEST force a TRUE pour utiliser l'adresse de test de CMCIC
	if ( (defined('_OGONE_TEST') AND _OGONE_TEST)
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
function ogone_url_serveur($config){

	if (ogone_is_sandbox($config))
		return "https://secure.ogone.com/ncol/test/orderstandard.asp";

	return "https://secure.ogone.com/ncol/prod/orderstandard.asp";
}


/**
 * Liste des cartes CB possibles selon la config
 * @param $config
 * @return array
 */
function ogone_available_cards($config){

	$cartes_possibles = array(
		'VISA'=>'VISA.gif',
		'MasterCard'=>'MASTERCARD.gif',
		'American Express'=>'AMEX.gif',
	);

	return $cartes_possibles;
}


/**
 * Signer les donnes envoyees a Ogone
 * @param array $contexte
 * @param array $config
 * @return string
 */
function ogone_sha_in($contexte, $config){
	$key = (isset($config['CLE_SHA_IN'])?$config['CLE_SHA_IN']:'sha-in');
	return ogone_signe_contexte(
		$contexte,
		$key,
		array(
			'ACCEPTURL','ADDMATCH','ADDRMATCH','ALIAS','ALIASOPERATION','ALIASUSAGE','ALLOWCORRECTION',
			'AMOUNT','AMOUNTHTVA','AMOUNTTVA','BACKURL','BGCOLOR','BRAND','BRANDVISUAL','BUTTONBGCOLOR',
			'BUTTONTXTCOLOR','CANCELURL','CARDNO','CATALOGURL','CERTID','CHECK_AAV','CIVILITY',
			'CN','COM','COMPLUS','COSTCENTER','CREDITCODE','CUID','CURRENCY','CVC','DATA','DATATYPE',
			'DATEIN','DATEOUT','DECLINEURL','DISCOUNTRATE','ECI','ECOM_BILLTO_POSTAL_CITY',
			'ECOM_BILLTO_POSTAL_COUNTRYCODE','ECOM_BILLTO_POSTAL_NAME_FIRST','ECOM_BILLTO_POSTAL_NAME_LAST',
			'ECOM_BILLTO_POSTAL_POSTALCODE','ECOM_BILLTO_POSTAL_STREET_LINE1','ECOM_BILLTO_POSTAL_STREET_LINE2',
			'ECOM_BILLTO_POSTAL_STREET_NUMBER','ECOM_CONSUMERID','ECOM_CONSUMERORDERID','ECOM_CONSUMERUSERALIAS',
			'ECOM_PAYMENT_CARD_EXPDATE_MONTH','ECOM_PAYMENT_CARD_EXPDATE_YEAR','ECOM_PAYMENT_CARD_NAME',
			'ECOM_PAYMENT_CARD_VERIFICATION','ECOM_SHIPTO_COMPANY','ECOM_SHIPTO_DOB','ECOM_SHIPTO_ONLINE_EMAIL',
			'ECOM_SHIPTO_POSTAL_CITY','ECOM_SHIPTO_POSTAL_COUNTRYCODE','ECOM_SHIPTO_POSTAL_NAME_FIRST',
			'ECOM_SHIPTO_POSTAL_NAME_LAST','ECOM_SHIPTO_POSTAL_POSTALCODE',
			'ECOM_SHIPTO_POSTAL_STREET_LINE1','ECOM_SHIPTO_POSTAL_STREET_LINE2',
			'ECOM_SHIPTO_POSTAL_STREET_NUMBER','ECOM_SHIPTO_TELECOM_FAX_NUMBER',
			'ECOM_SHIPTO_TELECOM_PHONE_NUMBER','ECOM_SHIPTO_TVA','ED','EMAIL','EXCEPTIONURL',
			'EXCLPMLIST','FIRSTCALL','FLAG3D','FONTTYPE','FORCECODE1','FORCECODE2','FORCECODEHASH',
			'FORCETP','GENERIC_BL','GIROPAY_ACCOUNT_NUMBER','GIROPAY_BLZ','GIROPAY_OWNER_NAME',
			'GLOBORDERID','GUID','HDFONTTYPE','HDTBLBGCOLOR','HDTBLTXTCOLOR','HEIGHTFRAME',
			'HOMEURL','HTTP_ACCEPT','HTTP_USER_AGENT','INCLUDE_BIN','INCLUDE_COUNTRIES',
			'INVDATE','INVDISCOUNT','INVLEVEL','INVORDERID','ISSUERID','LANGUAGE',
			'LEVEL1AUTHCPC','LIMITCLIENTSCRIPTUSAGE','LINE_REF','LIST_BIN','LIST_COUNTRIES',
			'LOGO','MERCHANTID','MODE','MTIME','MVER','OPERATION','OR_INVORDERID','OR_ORDERID',
			'ORDERID','ORIG','OWNERADDRESS','OWNERADDRESS2','OWNERCTY','OWNERTELNO','OWNERTOWN',
			'OWNERZIP','PAIDAMOUNT','PARAMPLUS','PARAMVAR','PAYID','PAYMETHOD','PM','PMLIST',
			'PMLISTPMLISTTYPE','PMLISTTYPE','PMLISTTYPEPMLIST','PMTYPE','POPUP','POST',
			'PSPID','PSWD','REF','REF_CUSTOMERID','REF_CUSTOMERREF','REFER','REFID','REFKIND',
			'REMOTE_ADDR','REQGENFIELDS','RTIMEOUT','RTIMEOUTREQUESTEDTIMEOUT','SCORINGCLIENT',
			'SETT_BATCH','SID','TAAL','TBLBGCOLOR','TBLTXTCOLOR','TID','TITLE','TOTALAMOUNT',
			'TP','TRACK2','TXTBADDR2','TXTCOLOR','TXTOKEN','TXTOKENTXTOKENPAYPAL','TYPE_COUNTRY',
			'UCAF_AUTHENTICATION_DATA','UCAF_PAYMENT_CARD_CVC2','UCAF_PAYMENT_CARD_EXPDATE_MONTH',
			'UCAF_PAYMENT_CARD_EXPDATE_YEAR','UCAF_PAYMENT_CARD_NUMBER','USERID','USERTYPE',
			'VERSION','WBTU_MSISDN','WBTU_ORDERID','WEIGHTUNIT','WIN3DS','WITHROOT'
		)
	);
}

/**
 * Signer les donnees recues de Ogone
 * @param array $contexte
 * @param array $config
 * @return string
 */
function ogone_sha_out($contexte, $config){
	$key = (isset($config['CLE_SHA_OUT'])?$config['CLE_SHA_OUT']:'sha-out');
	return ogone_signe_contexte(
		$contexte,
		$key,
		array(
			'AAVADDRESS','AAVCHECK','AAVZIP','ACCEPTANCE','ALIAS','AMOUNT','BRAND','CARDNO','CCCTY',
			'CN','COMPLUS','CURRENCY','CVCCHECK','DCC_COMMPERCENTAGE','DCC_CONVAMOUNT','DCC_CONVCCY',
			'DCC_EXCHRATE','DCC_EXCHRATESOURCE','DCC_EXCHRATETS','DCC_INDICATOR','DCC_MARGINPERCENTAGE',
			'DCC_VALIDHOUS','DIGESTCARDNO','ECI','ED','ENCCARDNO','IP','IPCTY','NBREMAILUSAGE',
			'NBRIPUSAGE','NBRIPUSAGE_ALLTX','NBRUSAGE','NCERROR','ORDERID','PAYID','PM',
			'SCO_CATEGORY','SCORING','STATUS','TRXDATE','VC'
		)
	);

}

/**
 * Signer le contexte en SHA, avec une cle secrete _OGONE_CLE_SHA_IN
 * @param array $contexte
 * @return string
 */
function ogone_signe_contexte($contexte,$secret,$parametres) {
	
	// on ne prend que les infos du contexte qui sont dans la liste des parametres
	// a signer
	$sign = array();
	foreach($contexte as $k=>$v) {
		$k = strtoupper($k);
		if (in_array($k,$parametres) AND strlen($v))
			$sign[$k] = $v;
	}
	ksort($sign);
	foreach($sign as $k=>$v) {
		$sign[$k] = "$k=$v$secret";
	}

	#var_dump($sign);
	$s = implode("",$sign);
	#var_dump($s);

	$s = strtoupper(sha1($s));
	return $s;
}

function ogone_get_response($config){
	$response = $_REQUEST;
	foreach ($_COOKIE as $key => $value)
		unset($response[$key]);

	// si pas de signature dans la reponse, la refuser
	if (!isset($response['SHASIGN'])){
		bank_transaction_invalide(0,
			array(
				'mode'=>"ogone",
				'erreur' => "reponse recue sans signature",
				'log' => var_export($response,true)
			)
		);
		return false;
	}

	if (
		$response['SHASIGN']!==ogone_sha_out($response,$config)
		// lorsque le nom est accentue, il faut le rencode utf8 pour que la signature concorde
		// il est double encode, donc on garde la reponse initiale qui est moins moche...
		// mais on accepte la reponse ainsi signee
	  AND $response['SHASIGN']!==ogone_sha_out(array_map('utf8_encode',$response),$config)
	){
		bank_transaction_invalide(0,
			array(
				'mode'=>"ogone",
				'erreur' => "signature invalide",
				'log' => var_export($response,true)
			)
		);
		return false;
	}

	unset($response['action']);
	unset($response['bankp']);

	return $response;
}


/**
 * Decoder la reponse renvoyee par Ogone
 *
 * @param array $response
 * @param string $mode
 * @return array
 */
function ogone_traite_reponse_transaction($response,$mode = 'ogone') {

/*
	'orderID' => string '15' (length=2)
  'currency' => string 'EUR' (length=3)
  'amount' => string '7' (length=1)
  'PM' => string 'CreditCard' (length=10)
  'ACCEPTANCE' => string 'test123' (length=7)
  'STATUS' => string '9' (length=1)
  'CARDNO' => string 'XXXXXXXXXXXX1111' (length=16)
  'ED' => string '1110' (length=4)
  'CN' => string 'John Doe' (length=12)
  'TRXDATE' => string '06/28/10' (length=8)
  'PAYID' => string '7599709' (length=7)
  'NCERROR' => string '0' (length=1)
  'BRAND' => string 'VISA' (length=4)
  'ECI' => string '7' (length=1)
  'IP' => string '88.173.4.97' (length=11)
  'SHASIGN' => string '6AC414390B39177A3EA9B70CE2D91BC03DED35F4' (length=40)
 */

	$id_transaction = intval($response['orderID']);
	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction))){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode'=>$mode,
				'erreur' => "transaction inconnue",
				'log' => var_export($response,true)
			)
		);
	}

	// ok, on traite le reglement
	$date=time();
	$date_paiement = date("Y-m-d H:i:s",$date);

	$erreur = ogone_response_code($response['STATUS'],$response['NCERROR']);
	$authorisation_id = $response['ACCEPTANCE'];
	$transaction = $response['PAYID'];

	if (!$transaction
	  OR !$authorisation_id
	  //OR $authorisation_id=='XXXXXX' // debug purpose
	  OR $erreur!==true){
	 	// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
	 	if ($row['reglee']=='oui') return array($id_transaction,true);

		return bank_transaction_echec($id_transaction,
			array(
				'mode'=>$mode,
				'date_paiement' => $date_paiement,
				'code_erreur' => $response['STATUS'].':'.$response['NCERROR'],
				'erreur' => $erreur,
				'log' => var_export($response,true),
			)
		);
	}

	// Ouf, le reglement a ete accepte

	// on verifie que le montant est bon !
	$montant_regle = floatval($response['amount']);
	if ($montant_regle!=$row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".var_export($response,true),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . '_reglements_partiels');

		// mais on continue en acceptant quand meme le paiement
		// car l'erreur est en general dans le traitement
	}

	sql_updateq("spip_transactions",array(
		"autorisation_id"=>"$transaction/$authorisation_id",
		"mode"=>$mode,
		"montant_regle"=>$montant_regle,
		"date_paiement"=>$date_paiement,
		"statut"=>'ok',
		"reglee"=>'oui'),
		"id_transaction=".intval($id_transaction)
	);
	spip_log("call_response : id_transaction $id_transaction, reglee",$mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));
	return array($id_transaction,true);
}

/**
 * Interpreter les codes d'erreur d'Ogone
 *
 * @param int $status
 * @param int $nccode
 * @return string
 */
function ogone_response_code($status,$nccode){
	if ($status==5 OR $status==9) return true; // pas d'erreur
	$codes = array(
		0 => "Demande de paiement invalide ou incomplete ($nccode)",
		1 => "Cancelled by client",
		2 => "Autorisation refusee ($nccode)",
		4 => "Order stored ($nccode)",
		41=> "Waiting client payment ($nccode)",
		5=>"Authorized ($nccode)",
		51 => "Autorisation en attente ($nccode)",
		52 => "Autorisation incertaine ($nccode)",
		59=>"Author. to get manually ($nccode)",
		6=>"Authorized and canceled ($nccode)",
		61=>"Author. deletion waiting ($nccode)",
		62=>"Author. deletion uncertain ($nccode)",
		63=>"Author. deletion refused ($nccode)",
		7=>"Payment deleted ($nccode)",
		71=>"Payment deletion pending ($nccode)",
		72=>"Payment deletion uncertain ($nccode)",
		73=>"Payment deletion refused ($nccode)",
		74=>"Payment deleted (not accepted) ($nccode)",
		75=>"Deletion processed by merchant ($nccode)",
		8=>"Refund ($nccode)",
		81=>"Refund pending ($nccode)",
		82=>"Refund uncertain ($nccode)",
		83=>"Refund refused ($nccode)",
		84=>"Payment declined by the acquirer (will be debited) ($nccode)",
		85=>"Refund processed by merchant ($nccode)",
		9=>"Payment requested ($nccode)",
		91 => "Paiement en attente ($nccode)",
		92 => "Paiement incertain ($nccode)",
		93 => "Paiement refuse ($nccode)",
		94=>"Refund declined by the acquirer ($nccode)",
		95=>"Payment processed by merchant ($nccode)",
		97=>"Being processed (intermediate technical status) ($nccode)",
		98=>"Being processed (intermediate technical status) ($nccode)",
		99=>"Being processed (intermediate technical status) ($nccode)",
	);
	if (isset($codes[intval($status)]))
		return $codes[intval($status)];
	return false;

}

/**
 * Code langue Ogone en fonction de la langue courante
 * 
 * @param string $lang
 * @return string
 */
function ogone_language_code($lang) {
	$codes = array(
		'en'=>'en_US', // for english
		'dk'=>'dk_DK', // for Danish
		'nl'=>'nl_NL', // for Dutch
		'be'=>'nl_BE', //  for Flemish
		'fr'=>'fr_FR', //  for French
		'de'=>'de_DE', //  for German
		'it'=>'it_IT', //  for Italian
		'jp'=>'ja_JP', //  for Japanese
		'no'=>'no_NO', //  for Norwegian
		'pl'=>'pl_PL', //  for Polish
		'pt'=>'pt_PT', //  for Portugese
		'es'=>'es_ES', //  for Spanish
		'se'=>'se_SE', //  for Swedish
		'tr'=>'tr_TR', //  for Turkish
	);

	if (isset($codes[$lang]))
		return $codes[$lang];
	$lang = $GLOBALS['meta']['langue_site'];
	if (isset($codes[$lang]))
		return $codes[$lang];

	// par defaut ...
	return $codes['fr'];
}
?>
