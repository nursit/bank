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

include_spip('inc/bank');


/**
 * Determiner l'URL d'appel serveur en fonction de la config
 *
 * @param array $config
 * @return string
 */
function payzen_url_serveur($config){

	$host = "";
	switch ($config['presta']) {
		case "systempay":

			switch ($config['service']) {
				case "osb":
					$host = "https://secure.osb.pf";
					break;
				case "systempay":
				case "cyberplus":
				case "spplus":
				default:
					$host = "https://paiement.systempay.fr";
					break;
			}
			break;

		case "clicandpay":
			$host = "https://clicandpay.groupecdn.fr";
			break;

		/*
		Provision. A voir si ce sont des presta ou des services...
		case "sogecommerce":
			$host = "https://sogecommerce.societegenerale.eu";
			break;

		case "scelliuspaiement":
			$host = "https://scelliuspaiement.labanquepostale.fr";
			break;
		*/

		case "payzen":
		default:
			$host = "https://secure.payzen.eu";
			break;
	}


	return "$host/vads-payment/";
}


/**
 * Determiner l'URL d'appel API REST en fonction de la config
 *
 * @param array $config
 * @return string
 */
function payzen_url_api($config){

	$url_serveur = payzen_url_serveur($config);
	$host = parse_url($url_serveur, PHP_URL_HOST);
	$url_serveur = explode($host, $url_serveur);

	$host = explode('.', $host);
	$subhost = array_shift($host);

	if (in_array($subhost, [ 'clicandpay' ])) {
		array_unshift($host, 'api-' . $subhost);
	}
	else {
		array_unshift($host, 'api');
	}
	$host = implode('.', $host);

	return reset($url_serveur) . $host;

}

/**
 * Appel d'une methode GET de l'API REST PayZen
 * https://payzen.io/fr-FR/rest/V4.0/api/
 *
 * @param array $config
 * @param string $method
 * @param array $params
 * @return array|bool|float|int|mixed|stdClass|string
 * @throws \Lyra\Exceptions\LyraException
 */
function payzen_api_call($config, $method, $params) {
	static $client;
	if (!$client) {
		/**
		 * Get the client
		 */
		include_spip("presta/payzen/lib/lyracom/rest-php-sdk/src/autoload");

		$client = new Lyra\Client();
	}

	/**
	 * Define configuration
	 */

	/* Username, password and endpoint used for server to server web-service calls */
	$client->setUsername($config['SITE_ID']);
	$client->setPassword(payzen_api_password($config));
	$client->setEndpoint(payzen_url_api($config));

	$path = "V4/" . trim($method,'/');

	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}
	$mode .= '_api';
	spip_log("Request API $method ".json_encode($params), $mode . _LOG_DEBUG);
	try {
		$response = $client->post($path, $params);
	}
	catch (Exception $e) {
		spip_log("ECHEC appel API $method ".json_encode($params) . " :" . $e->getMessage(), $mode . _LOG_ERREUR);
		return false;
	}

	spip_log("Response API $method ".json_encode($response), $mode . _LOG_DEBUG);

	return $response;
}

/**
 * Determiner la cle de signature en fonction de la config
 * @param array $config
 * @return string
 */
function payzen_key($config){
	if ($config['mode_test']){
		return (empty($config['CLE_test']) ? '' : $config['CLE_test']);
	}

	return (empty($config['CLE']) ? '' : $config['CLE']);
}

/**
 * Determiner la methode de signature en fonction de la config
 * @param array $config
 * @return string
 */
function payzen_sign_algorithm($config){
	// historique : assurer la continuite sur les boutiques en prod
	if (empty($config['SIGNATURE_ALGO'])
	  or !in_array($config['SIGNATURE_ALGO'], ['sha1', 'sha256'])) {
		return 'sha1';
	}
	return $config['SIGNATURE_ALGO'];
}

/**
 * Determiner la cle de signature en fonction de la config
 * @param array $config
 * @return string
 */
function payzen_api_password($config){
	if ($config['mode_test']){
		return (empty($config['API_PASSWORD_test']) ? '' : $config['API_PASSWORD_test']);
	}

	return (empty($config['API_PASSWORD']) ? '' : $config['API_PASSWORD']);
}


/**
 * Liste des cartes CB possibles selon la config
 * @param $config
 * @return array
 */
function payzen_available_cards($config){

	$mode = $config['presta'];
	$cartes_possibles = array(
		'CB' => "CB.gif",
		'VISA' => "VISA.gif",
		'MASTERCARD' => "MASTERCARD.gif",
		'AMEX' => "AMEX.gif",
	);

	if ($config['presta']=='systempay') {
		if ($config['service']=="osb"){
			// pas de CB et e-CB avec OSB
			unset($cartes_possibles['CB']);
		} else {
			if ($config['type']!=='abo'){
				$cartes_possibles['MAESTRO'] = "MAESTRO.gif";
				$cartes_possibles['VISA_ELECTRON'] = "VISAELECTRON.gif";
				//$cartes_possibles['PAYPAL']="PAYPAL.gif";
				//$cartes_possibles['V_ME']="VME.gif";
			}
		}
	}

	if (in_array($config['presta'], ['payzen', 'clicandpay'])){
		// les SEPA, abo ou non
		$cartes_possibles['SDD'] = "SEPA_SDD.gif";
		if ($config['type']!=='abo'){
			$cartes_possibles['PAYLIB'] = "PAYLIB.gif";
			$cartes_possibles['ONEY'] = "ONEY.gif";
			$cartes_possibles['JCB'] = "JCB.gif";
			$cartes_possibles['DINERS'] = "DINERS.gif";
			$cartes_possibles['SOFORT_BANKING'] = "SOFORT.gif";
			$cartes_possibles['IDEAL'] = "IDEAL.gif";

			// et les e-cheques vacances
			// $cartes_possibles['E_CV'] = "E_CV.gif";
		}
	}

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
function payzen_form_hidden($config, $parms){
	$parms['signature'] = payzen_signe_contexte($parms, payzen_key($config), payzen_sign_algorithm($config));
	$hidden = "";
	foreach ($parms as $k => $v){
		$hidden .= "<input type='hidden' name='$k' value='" . str_replace("'", "&#39;", $v) . "' />";
	}

	return $hidden;
}


/**
 * Signer le contexte en SHA, avec une cle secrete $key
 * @param array $contexte
 * @param string $key
 * @param string $algorithm
 * @return string
 */
function payzen_signe_contexte($contexte, $key, $algorithm = 'sha1'){

	// on ne prend que les infos vads_*
	// a signer
	$sign = array();
	foreach ($contexte as $k => $v){
		if (strncmp($k, 'vads_', 5)==0){
			$sign[$k] = $v;
		}
	}
	// tri des parametres par ordre alphabétique
	ksort($sign);
	$contenu_signature = implode("+", $sign);
	$contenu_signature .= "+$key";

	switch ($algorithm) {
		case 'sha1':
			$s = sha1($contenu_signature);
			break;

		case 'sha256':
		default:
			$s = base64_encode(hash_hmac('sha256',$contenu_signature, $key, true));
			break;
	}

	return $s;
}


/**
 * Verifier la signature de la reponse PayZen
 * @param array $values
 * @param string $key
 * @param string $algorithm
 * @return bool
 */
function payzen_verifie_signature($values, $key, $algorithm = 'sha1'){
	$signature = payzen_signe_contexte($values, $key, $algorithm);

	if (isset($values['signature'])
		AND ($values['signature']==$signature)){

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
function payzen_recupere_reponse($config){
	$reponse = array();
	foreach ($_REQUEST as $k => $v){
		if (strncmp($k, 'vads_', 5)==0){
			$reponse[$k] = $v;
		}
	}
	$reponse['signature'] = (isset($_REQUEST['signature']) ? $_REQUEST['signature'] : '');

	$ok = payzen_verifie_signature($reponse, payzen_key($config), payzen_sign_algorithm($config));
	// si signature invalide, verifier si
	// on rejoue manuellement un call vads_url_check_src=RETRY incomplet
	// en lui ajoutant le vads_subscription
	if (!$ok
		AND isset($reponse['vads_url_check_src'])
		AND $reponse['vads_url_check_src']==='RETRY'
		AND isset($reponse['vads_subscription'])){
		$response_part = $reponse;
		unset($response_part['vads_subscription']);
		$ok = payzen_verifie_signature($response_part, payzen_key($config), payzen_sign_algorithm($config));
	}
	if (!$ok){
		spip_log("recupere_reponse : signature invalide " . var_export($reponse, true), $config['presta'] . _LOG_ERREUR);
		return false;
	}

	return $reponse;
}


/**
 * Traiter la reponse
 * @param array $config
 * @param array $response
 * @return array
 */
function payzen_traite_reponse_transaction($config, $response){
	#var_dump($response);
	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}
	$config_id = bank_config_id($config);

	$id_transaction = $response['vads_order_id'];
	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction))){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => bank_shell_args($response)
			)
		);
	}

	$is_sepa = (isset($response['vads_card_brand']) AND $response['vads_card_brand']=="SDD");
	$is_payment = true;
	$is_registering = false;
	$is_subscribing = false;

	// si c'est une souscription ou un register, lever les bons flags
	// si pas de paiement on veut enregistrer les donnees et sortir de la sans generer d'erreur (le paiement arrivera plus tard)
	if ($response['vads_page_action']
		AND in_array($response['vads_page_action'], array('REGISTER', 'REGISTER_SUBSCRIBE', 'REGISTER_PAY_SUBSCRIBE', 'SUBSCRIBE'))){
		$is_registering = true;
		if ($response['vads_page_action']!=='REGISTER_PAY_SUBSCRIBE'){
			$is_payment = false;
		}
		if ($response['vads_page_action']!=='REGISTER'){
			$is_subscribing = true;
		}
	} // cas appel depuis BO
	elseif (in_array($response['vads_url_check_src'], array('BO', 'REC', 'RETRY'))) {
		if (isset($response['vads_identifier']) AND $response['vads_identifier']){
			$is_registering = true;
		}
		if (isset($response['vads_subscription']) AND $response['vads_subscription']){
			$is_subscribing = true;
		}
		// si on a pas de vads_subscription renseigne, mais bien un vads_identifier ET vads_sequence_number
		// on note que c'est bien un $is_subscribing pour lever une erreur paiement invalide par email
		// il faudra le traiter a la main
		// car c'est un bug chez PayZen qui oublie d'envoyer l'info vads_subscription dans ce cas
		elseif ($is_registering
			AND !isset($response['vads_subscription'])
			AND isset($response['vads_recurrence_number']) AND $response['vads_recurrence_number']) {
			$is_subscribing = true;
			if (!$response['vads_card_number']){
				$response['vads_card_number'] = 'X_X';
			}
		}
	}


	// si c'est un debit, a-t-on bien l'operation attendue ?
	if ($is_payment
		AND $response['vads_operation_type']!=="DEBIT"
		// et pas un Abandon ou Refus
		AND !in_array($response['vads_trans_status'], array('ABANDONED', 'NOT_CREATED', 'REFUSED'))){
		// si la transaction est deja reglee, ne pas la modifier, c'est OK
		if ($row['statut']=='ok'){
			return array($id_transaction, true);
		}
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "vads_operation_type=" . $response['vads_operation_type'] . " non prise en charge",
				'log' => bank_shell_args($response),
				'sujet' => "Operation invalide",
				'update' => true,
			)
		);
	}


	// ok, on traite le reglement
	$date = $response['vads_effective_creation_date'];
	// si c'est un paiement SEPA, on prend la date de presentation du SEPA comme date de paiement
	// (date_paiement dans le futur donc)
	if ($is_sepa){
		$date = $response['vads_presentation_date'];
	}


	// date paiement et date transaction
	$t = gmmktime(
		substr($date, 8, 2), //Heures
		substr($date, 10, 2), //min
		substr($date, 12, 2), //sec
		substr($date, 4, 2), //mois
		substr($date, 6, 2), //jour
		substr($date, 0, 4) //annee
	);
	$date_paiement = date('Y-m-d H:i:s', $t);
	$date_transaction = $date_paiement;
	if (isset($response['vads_presentation_date'])){
		$date = $response['vads_trans_date'];
		$t = gmmktime(
			substr($date, 8, 2), //Heures
			substr($date, 10, 2), //min
			substr($date, 12, 2), //sec
			substr($date, 4, 2), //mois
			substr($date, 6, 2), //jour
			substr($date, 0, 4) //annee
		);
		$date_transaction = date('Y-m-d H:i:s', $t);
	}

	$erreur = array(
		payzen_response_code($response['vads_result']),
		payzen_auth_response_code($response['vads_auth_result'])
	);

	$erreur = array_filter($erreur);
	$erreur = trim(implode(' ', $erreur));

	$authorisation_id = $response['vads_auth_number'];
	$transaction = $response['vads_payment_certificate'];

	// si c'est un SEPA, on a pas encore la transaction et le numero d'autorisation car il y a un delai avant presentation
	// (paiement dans le futur)
	if ($is_sepa AND !$transaction){
		list($transaction, $authorisation_id) = explode("_", $response['vads_card_number']);
	}

	if ($is_payment AND !$erreur AND !in_array($response['vads_trans_status'], array('AUTHORISED', 'CAPTURED', 'WAITING_AUTHORISATION'))){
		$erreur = "vads_trans_status " . $response['vads_trans_status'] . " (!IN AUTHORISED,CAPTURED,WAITING_AUTHORISATION)";
	}
	if (!$erreur AND $is_payment AND !$transaction){
		$erreur = "pas de vads_payment_certificate";
	}
	if (!$erreur AND !$authorisation_id){
		$erreur = "pas de vads_auth_number";
	}

	if ($erreur){
		// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
		if ($row['reglee']=='oui'){
			return array($id_transaction, true);
		}
		// sinon enregistrer l'absence de paiement et l'erreur
		return bank_transaction_echec($id_transaction,
			array(
				'mode' => $mode,
				'config_id' => $config_id,
				'date_paiement' => $date_paiement,
				'code_erreur' => $response['vads_result'],
				'erreur' => $erreur,
				'log' => bank_shell_args($response),
				'send_mail' => intval($response['vads_result'])==2,
			)
		);
	}

	$set = array(
		"autorisation_id" => "$authorisation_id/$transaction",
		"mode" => "$mode/$config_id",
	);

	if ($is_payment){
		// Ouf, le reglement a ete accepte
		// on verifie que le montant est bon !
		$montant_regle = $response['vads_effective_amount']/100;
		if ($montant_regle!=$row['montant']){
			spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=" . $row['montant'] . ":" . bank_shell_args($response), $mode);
			// on log ca dans un journal dedie
			spip_log($t, $mode . '_reglements_partiels');
		}
		$set['montant_regle'] = $montant_regle;
		$set['date_paiement'] = $date_paiement;
		$set['statut'] = 'ok';
		$set['reglee'] = 'oui';
	} else {
		$set['statut'] = 'attente';
	}

	// si la date de transaction Systempay est anterieure a celle du site - 1h, on la met a jour
	// (cas ou l'on rejoue a posteriori une notification qui n'a pas marche)
	if ($date_transaction<$row['date_transaction']
		OR $date_paiement<$row['date_transaction']){
		$set['date_transaction'] = $date_transaction;
	}

	// si on a les infos de validite / card number, on les note ici
	if (isset($response['vads_expiry_year'])){
		$set['validite'] = $response['vads_expiry_year'] . "-" . $response['vads_expiry_month'];
	}
	if (isset($response['vads_card_brand']) OR isset($response['vads_card_number'])){
		// par defaut on note brand et number dans refcb
		// mais ecrase si le paiement a genere un identifiant de paiement
		// qui peut etre reutilise
		$set['refcb'] = '';
		if (isset($response['vads_card_brand'])){
			$set['refcb'] = $response['vads_card_brand'];
			if ($set['refcb']==="SDD"){
				$set['refcb'] = "SEPA";
			} // more user friendly
		}
		if (isset($response['vads_card_number'])){
			$set['refcb'] .= " " . $response['vads_card_number'];
		}
		$set['refcb'] = trim($set['refcb']);
	}


	// si vads_identifier fourni on le note dans refcb : c'est un identifiant de paiement
	if (isset($response['vads_identifier']) AND $response['vads_identifier']){
		$set['pay_id'] = $response['vads_identifier'];
	} // si c'est un enregistrement on a une erreur si pas d'identifier
	elseif ($is_registering) {
		// si pas de paiement, on genere un echec
		if (!$is_payment){
			return bank_transaction_echec($id_transaction,
				array(
					'mode' => $mode,
					'config_id' => $config_id,
					'date_paiement' => $date_paiement,
					'erreur' => "Pas de vads_identifier sur operation " . $response['vads_operation_type'],
					'log' => bank_shell_args($response),
				)
			);
		} else {
			// sinon on enregistre l'erreur et on log+mail mais on fini le paiement en OK quand meme
			$set['erreur'] = "Pas de vads_identifier sur operation " . $response['vads_operation_type'];
			bank_transaction_invalide($id_transaction,
				array(
					'mode' => $mode,
					'sujet' => 'Echec REGISTER',
					'erreur' => $set['erreur'],
					'log' => bank_shell_args($response),
				)
			);
		}
	}

	// si on a un numero d'abonnement on le note dans abo_uid
	if (isset($response['vads_subscription']) AND $response['vads_subscription']){
		$set['abo_uid'] = $response['vads_subscription'];
	} // si c'est un abonnement on a une erreur si pas de vads_subscription
	elseif ($is_subscribing) {
		// si pas de paiement, on genere un echec
		if (!$is_payment){
			return bank_transaction_echec($id_transaction,
				array(
					'mode' => $mode,
					'config_id' => $config_id,
					'date_paiement' => $date_paiement,
					'erreur' => "Pas de vads_subscription sur operation " . $response['vads_operation_type'],
					'log' => bank_shell_args($response),
				)
			);
		} else {
			// sinon on enregistre l'erreur et on log+mail mais on fini le paiement en OK quand meme
			$set['erreur'] = "Pas de vads_subscription sur operation " . $response['vads_operation_type'];
			bank_transaction_invalide($id_transaction,
				array(
					'mode' => $mode,
					'sujet' => 'Echec SUBSCRIBE',
					'erreur' => $set['erreur'],
					'log' => bank_shell_args($response),
				)
			);
		}
	}

	// OK on met a jour la transaction en base
	sql_updateq("spip_transactions", $set, "id_transaction=" . intval($id_transaction));
	spip_log("call_response : id_transaction $id_transaction, reglee", $mode);


	// si on dispose des informations utilisateurs, les utiliser pour peupler la gloable bank_session
	// qui peut etre utilisee pour creer le compte client a la volee
	$var_users = array('vads_cust_email' => 'email', 'vads_cust_name' => 'nom', 'vads_cust_title' => 'civilite');
	foreach ($var_users as $kr => $ks){
		if (isset($response[$kr]) AND $response[$kr]){
			if (!isset($GLOBALS['bank_session'])){
				$GLOBALS['bank_session'] = array();
			}
			$GLOBALS['bank_session'][$ks] = $response[$kr];
		}
	}

	// si transaction reglee, on poursuit le processus
	if (isset($set['reglee']) AND $set['reglee']=='oui'){
		$regler_transaction = charger_fonction('regler_transaction', 'bank');
		$regler_transaction($id_transaction, array('row_prec' => $row));
		$res = true;
	} else {
		$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction));
		pipeline('trig_bank_reglement_en_attente', array(
				'args' => array(
					'statut' => 'attente',
					'mode' => $row['mode'],
					'type' => $row['abo_uid'] ? 'abo' : 'acte',
					'id_transaction' => $id_transaction,
					'row' => $row,
				),
				'data' => '')
		);
		$res = 'wait';
	}

	// c'est un succes
	return array($id_transaction, $res);
}


function payzen_response_code($code){
	if ($code==0){
		return '';
	}
	$pre = 'Erreur : ';
	$codes = array(
		2 => 'Le commerçant doit contacter la banque du porteur.',
		5 => 'Paiement refusé.',
		17 => 'Annulation du client',
		30 => 'Erreur de format de la requête. A mettre en rapport avec la valorisation du champ vads_extra_result.',
		96 => 'Erreur technique lors du paiement.',
	);

	if (isset($codes[intval($code)])){
		return $pre . $codes[intval($code)];
	}
	return $pre ? $pre : 'Erreur inconnue';
}

function payzen_auth_response_code($code){
	if ($code==0){
		return '';
	}
	$pre = 'Autorisation refusee : ';
	$codes = array(
		2 => 'contacter l’emetteur de carte',
		3 => 'accepteur invalide',
		4 => 'conserver la carte',
		5 => 'ne pas honorer',
		7 => 'conserver la carte, conditions speciales',
		8 => 'approuver apres identification',
		12 => 'transaction invalide',
		13 => 'montant invalide',
		14 => 'numero de porteur invalide',
		15 => 'Emetteur de carte inconnu',
		17 => 'Annulation client',
		19 => 'Repeter la transaction ulterieurement',
		20 => 'Reponse erronee (erreur dans le domaine serveur)',
		24 => 'Mise a jour de fichier non supportee',
		25 => 'Impossible de localiser l’enregistrement dans le fichier',
		26 => 'Enregistrement duplique, ancien enregistrement remplace',
		27 => 'Erreur en « edit » sur champ de lise a jour fichier',
		28 => 'Acces interdit au fichier',
		29 => 'Mise a jour impossible',
		30 => 'erreur de format',
		31 => 'identifiant de l’organisme acquereur inconnu',
		33 => 'date de validite de la carte depassee',
		34 => 'suspicion de fraude',
		38 => 'Date de validite de la carte depassee',
		41 => 'carte perdue',
		43 => 'carte volee',
		51 => 'provision insuffisante ou credit depasse',
		54 => 'date de validite de la carte depassee',
		55 => 'Code confidentiel errone',
		56 => 'carte absente du fichier',
		57 => 'transaction non permise a ce porteur',
		58 => 'transaction interdite au terminal',
		59 => 'suspicion de fraude',
		60 => 'l’accepteur de carte doit contacter l’acquereur',
		61 => 'montant de retrait hors limite',
		63 => 'regles de securite non respectees',
		68 => 'reponse non parvenue ou reçue trop tard',
		75 => 'Nombre d’essais code confidentiel depasse',
		76 => 'Porteur deja en opposition, ancien enregistrement conserve',
		90 => 'arrêt momentane du systeme',
		91 => 'emetteur de cartes inaccessible',
		94 => 'transaction dupliquee',
		96 => 'mauvais fonctionnement du systeme',
		97 => 'echeance de la temporisation de surveillance globale',
		98 => 'serveur indisponible routage reseau demande a nouveau',
		99 => 'incident domaine initiateur'
	);

	if (isset($codes[intval($code)])){
		return $pre . $codes[intval($code)];
	}
	return $pre ? $pre : 'Erreur inconnue';
}
