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
 * Determiner le mode test en fonction d'un define ou de la config
 * @param array $config
 * @return bool
 */
function paybox_is_sandbox($config){
	$test = false;
	// _PAYBOX_SANDBOX force a TRUE pour utiliser l'adresse de test de CMCIC
	if ((defined('_PAYBOX_SANDBOX') AND _PAYBOX_SANDBOX)
		OR (isset($config['mode_test']) AND $config['mode_test'])){
		$test = true;
	}
	return $test;
}

/**
 * Determiner le host pour les appels Paybox : sandbox, host principal ou host fallback
 * @param $config
 * @return string
 */
function paybox_url_host($config){
	$mode = $config['presta'];
	static $check_load_ok;
	// mode sandbox ? possibilite de le forcer par define
	if (paybox_is_sandbox($config)){
		return "https://preprod-tpeweb.paybox.com/";
	}

	// tester la dispo de https://tpeweb.paybox.com/load.html avec timeout faible
	// cf https://github.com/nursit/bank/issues/7
	// else $host = "https://tpeweb1.paybox.com/";

	if (is_null($check_load_ok)){
		// dans le doute on dit que c'est tpeweb
		$check_load_ok = "https://tpeweb.paybox.com/";
		// si on a curl_init, on test tpeweb puis tpeweb1, pour choisir celui qui est fonctionnel
		if (function_exists('curl_init')){
			foreach (["https://tpeweb.paybox.com/", "https://tpeweb1.paybox.com/"] as $h){
				$url = $h . "load.html";
				//setting the curl parameters.
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				//curl_setopt($ch, CURLOPT_VERBOSE, 1);
				curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

				// un timeout court de 2s, car on veut pas attendre longtemps apres le serveur si jamais il est en indispo
				curl_setopt($ch, CURLOPT_TIMEOUT_MS, 2000);

				//turning off the server and peer verification(TrustManager Concept).
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				if (!defined('CURL_SSLVERSION_TLSv1_2')){
					define('CURL_SSLVERSION_TLSv1_2', 6);
				}
				curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
				curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);

				$user_agent = "SPIP/Bank";
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close', "User-Agent: $user_agent"));

				//getting response from server
				$response = curl_exec($ch);
				$erreur = curl_errno($ch);
				// si pas d'erreur et si OK dans la reponse on retient ce host comme serveur de paiement
				// si aucun serveur ne repond correctement sur /load.html c'est probablement notre connexion sortante qui est pas fonctionnelle
				// dans ce cas on reste sur le serveur par defaut
				if (!$erreur){
					//closing the curl
					curl_close($ch);
					if ($response and strpos($response, "OK")!==false){
						spip_log("Reponse OK pour $url, on choisit $h", $mode . _LOG_INFO_IMPORTANTE);
						$check_load_ok = $h;
						break;
					}
				}
			}
		}
	}

	$host = $check_load_ok;

	return $host;

}

/**
 * Liste des cartes CB possibles selon la config
 * @param $config
 * @return array
 */
function paybox_available_cards($config){

	$cartes_possibles = array(
		'CB' => 'CB.gif',
		'VISA' => 'VISA.gif',
		'EUROCARD_MASTERCARD' => 'MASTERCARD.gif',
	);
	if ($config['type']!=='abo'){
		$cartes_possibles['AMEX'] = 'AMEX.gif';
		$cartes_possibles['AURORE'] = 'AURORE.gif';
	}

	return $cartes_possibles;
}

/**
 * URL d'appel pour le paiement en fonction de la config
 * @param $config
 * @return string
 */
function paybox_url_paiment($config){
	return paybox_url_host($config) . "cgi/MYchoix_pagepaiement.cgi";
}

/**
 * URL d'appel pour la resiliation en fonction de la config
 * @param $config
 * @return string
 */
function paybox_url_resil($config){
	return paybox_url_host($config) . "cgi-bin/ResAbon.cgi";
}

/**
 * URL d'appel pour le paiement directplus
 * @param $config
 * @return string
 */
function paybox_url_directplus($config){
	// pas de sandbox ni de host de repli ?
	return "https://ppps.paybox.com/PPPS.php";
}


function paybox_shell_args($params){
	return bank_shell_args($params);
}

/**
 * Generer les hidden du formulaire d'envoi a paybox
 * selon methode cle secrete + hash ou binaire executable
 *
 * @param $params
 * @return array|string
 */
function paybox_form_hidden($params){

	if (isset($params['PBX_HMAC_KEY']) AND !trim($params['PBX_HMAC_KEY'])){
		unset($params['PBX_HMAC_KEY']);
	}

	if (paybox_is_sandbox($params) AND isset($params['PBX_HMAC_KEY_test'])){
		$params['PBX_HMAC_KEY'] = $params['PBX_HMAC_KEY_test'];
	}
	// cle de test, on la vire de toute facon
	if (isset($params['PBX_HMAC_KEY_test'])){
		unset($params['PBX_HMAC_KEY_test']);
	}

	// methode hash avec cle secrete partagee fournie
	if (isset($params['PBX_HMAC_KEY'])){
		$key = trim($params['PBX_HMAC_KEY']);
		unset($params['PBX_HMAC_KEY']);

		if (isset($params['DIRECT_PLUS_CLE'])){
			unset($params['DIRECT_PLUS_CLE']);
		}

		if (!isset($params['PBX_TIME'])){
			$params['PBX_TIME'] = date("c");
		}

		// On calcule l?empreinte (a renseigner dans le parametre PBX_HMAC) grace a la fonction hash_hmac et
		// la cle binaire
		// On envoie via la variable PBX_HASH l'algorithme de hachage qui a ete utilise (SHA512 dans ce cas)
		// Pour afficher la liste des algorithmes disponibles sur votre environnement, decommentez la ligne
		// suivante
		// print_r(hash_algos());
		$hash_method = "sha512";
		if (function_exists("hash_algos")){
			$algos = hash_algos();
			foreach (array("sha512", "sha256", "sha1", "md5") as $method){
				if (in_array($method, $algos)){
					$hash_method = $method;
					break;
				}
			}
		}
		$params['PBX_HASH'] = strtoupper($hash_method);


		// On cree la chaine a hacher sans URLencodage
		$message = array();
		$params_att = array();
		foreach ($params as $k => $v){
			if (strncmp($k, "PBX_", 4)==0){
				// il faut urlencoder les quotes en %22 et pas les echapper en entites html
				$params_att[$k] = str_replace('"', '%22', $v);
				$message[] = "$k=" . $params_att[$k];
			}
		}
		$message = implode("&", $message);

		// la cle secrete HMAC
		// Si la cle est en ASCII, On la transforme en binaire
		$binKey = pack("H*", $key);

		// La chaine sera envoyee en majuscules, d'ou l'utilisation de strtoupper()
		$hmac = strtoupper(hash_hmac($hash_method, $message, $binKey));

		$params_att['PBX_HMAC'] = $hmac;

		// On cree les hidden du formulaire a envoyer a Paybox System
		// ATTENTION : l'ordre des champs est extremement important, il doit
		// correspondre exactement a l'ordre des champs dans la chaine hachee
		$hidden = array();
		foreach ($params_att as $k => $v){
			$hidden[] = "<input type=\"hidden\" name=\"$k\" value=\"$v\" />";
		}
		$hidden = implode("\n", $hidden);
		return $hidden;
	}

	// sinon methode encodage par binaire
	// depreciee mais permet transition en douceur et iso-fonctionnelle
	$paybox_exec_request = charger_fonction("exec_request", "presta/paybox");
	$hidden = $paybox_exec_request($params);
	// modulev2.cgi injecte une balise Form dont on ne veut pas ici
	$hidden = preg_replace(",<form[^>]*>,Uims", "", $hidden);
	return $hidden;
}


function paybox_response($response = 'response'){

	$url = parse_url($_SERVER['REQUEST_URI']);
	if (function_exists('openssl_pkey_get_public')){
		// recuperer la signature
		$sign = _request('sign');
		$sign = base64_decode($sign);
		// recuperer les variables
		$vars = $url['query'];

		// cas ou l'on rejoue une transaction a une date anterieure
		// on peut y ajouter un &var_replay_date=2016-03-25 pour prendre en compte cette date
		// de transaction/paiement
		if (_request('var_replay_date')){
			$vars = preg_replace(',&var_replay_date=.*$,', '', $vars);
			// $_SERVER['REQUEST_TIME'] sera modifie plus bas, une fois que la signature aura ete verifiee
		}

		// enlever la signature des data
		$vars = preg_replace(',&sign=.*$,', '', $vars);

		// une variante sans &action=...
		// car l'autoresponse ne la prend pas en compte, mais la response directe la prend en compte
		// $vars1 = preg_replace(',^[^?]*?page=[^&]*&,','',$vars);
		$vars1 = preg_replace(',^[^?]*?action=[^&]*&,', '', $vars);
		$vars1 = preg_replace(',^[^?]*?bankp=[^&]*&,', '', $vars1);

		// recuperer la cle publique Paybox
		// on utilise find_in_path pour permettre surcharge au cas ou la cle changerait
		$pubkey = "";
		if ($keyfile = find_in_path("presta/paybox/inc/pubkey.pem")){
			lire_fichier($keyfile, $pubkey);
		}
		// verifier la signature avec $vars ou $vars1
		if (!$pubkey
			OR !$cle = openssl_pkey_get_public($pubkey)
			OR !(openssl_verify($vars, $sign, $cle) OR openssl_verify($vars1, $sign, $cle))
		){
			bank_transaction_invalide(0,
				array(
					'mode' => "paybox",
					'erreur' => "signature invalide",
					'log' => var_export($url, true)
				)
			);
			return false;
		}
	} else {
		if (!_request('sign')){
			bank_transaction_invalide(0,
				array(
					'mode' => "paybox",
					'erreur' => "reponse sans signature",
					'log' => var_export($url, true)
				)
			);
			return false;
		}
		// on ne sait pas verifier la signature, on laisse passer mais on mail le webmestre
		bank_transaction_invalide(0,
			array(
				'mode' => "paybox",
				'erreur' => "Impossible de verifier la signature, fonction openssl_pkey_get_public inconnue",
				'log' => var_export($url, true),
				'sujet' => 'Paiement non securise',
			)
		);
	}

	parse_str($url['query'], $response);
	unset($response['page']);
	unset($response['sign']);

	// cas ou l'on rejoue une transaction a une date anterieure
	// on peut y ajouter un &var_replay_date=2016-03-25 pour prendre en compte cette date
	// de transaction/paiement
	if (_request('var_replay_date')){
		$_SERVER['REQUEST_TIME'] = strtotime(_request('var_replay_date'));
	}

	return $response;
}

/**
 * @param array $config
 * @param array $response
 * @return array
 */
function paybox_traite_reponse_transaction($config, $response){
	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}
	$config_id = bank_config_id($config);

	// $response['id_transaction'] Peut contenir /email ou IBSxx... en cas d'abo
	$id_transaction = intval($response['id_transaction']);
	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction))){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => paybox_shell_args($response)
			)
		);
	}

	// On peut maintenant connaître la devise et ses infos
	$devise_fraction = bank_devise_info($row['devise'], 'fraction');

	// ok, on traite le reglement
	$date = $_SERVER['REQUEST_TIME'];
	$date_paiement = sql_format_date(
		date('Y', $date), //annee
		date('m', $date), //mois
		date('d', $date), //jour
		date('H', $date), //Heures
		date('i', $date), //min
		date('s', $date) //sec
	);

	$erreur = paybox_response_code($response['erreur']);
	$authorisation_id = $response['auth'];
	$transaction = $response['trans'];

	if (!$transaction
		OR !$authorisation_id
//	 OR $authorisation_id=='XXXXXX'
		OR $erreur!==true){
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
				'code_erreur' => $response['erreur'],
				'erreur' => $erreur,
				'log' => paybox_shell_args($response),
				'send_mail' => in_array($response['erreur'], array(3, 6)) ? true : false,
			)
		);
	}

	// Ouf, le reglement a ete accepte

	// on verifie que le montant est bon !
	$montant_regle = $response['montant'] / (10**$devise_fraction);
	if ($montant_regle!=$row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=" . $row['montant'] . ":" . paybox_shell_args($response), $mode);
		// on log ca dans un journal dedie
		spip_log($t, $mode . '_reglements_partiels');
	}

	$set = array(
		"autorisation_id" => "$transaction/$authorisation_id",
		"mode" => "$mode/$config_id",
		"montant_regle" => $montant_regle,
		"date_paiement" => $date_paiement,
		"statut" => 'ok',
		"reglee" => 'oui');


	// type et numero de carte ?
	if (isset($response['carte']) OR isset($response['BIN6'])){
		// par defaut on note carte et BIN6 dans refcb + le type de 3DS utilisé
		$set['refcb'] = '';
		if (isset($response['carte'])){
			$set['refcb'] .= $response['carte'];
		}
		if (isset($response['BIN6'])){
			$set['refcb'] .= " " . $response['BIN6'];
		}
		if (isset($response['3DS'])){
			$set['refcb'] .= " 3DS:" . $response['3DS'];
		}
		$set['refcb'] = trim($set['refcb']);
	}
	// validite de carte ?
	if (isset($response['valid']) AND $response['valid']){
		$set['validite'] = "20" . substr($response['valid'], 0, 2) . "-" . substr($response['valid'], 2, 2);
	}

	// si on a envoye un U il faut recuperer les donnees CB et les stocker sur le compte client
	if (isset($response['ppps']) AND $response['ppps']){
		$set['pay_id'] = $response['ppps'];
	}

	// si abonnement, stocker les 2 infos importantes : uid et validite
	if (isset($response['abo']) AND $response['abo']){
		$set['abo_uid'] = $response['abo'];
	}

	// il faudrait stocker le $transaction aussi pour d'eventuels retour vers paybox ?
	sql_updateq("spip_transactions", $set, "id_transaction=" . intval($id_transaction));
	spip_log("call_response : id_transaction $id_transaction, reglee", $mode);

	$regler_transaction = charger_fonction('regler_transaction', 'bank');
	$regler_transaction($id_transaction, array('row_prec' => $row));
	return array($id_transaction, true);
}

function paybox_response_code($code){
	if ($code==0){
		return true;
	}
	$pre = "";
	if ($code>100 AND $code<199){
		$pre = 'Autorisation refusee : ';
	}
	$codes = array(
		1 => 'La connexion au centre d\'autorisation a echoue',
		3 => 'Erreur Paybox',
		4 => 'Numero de porteur ou cryptogramme visuel invalide',
		6 => 'Acces refuse ou site/rang/identifiant invalide',
		8 => 'Date de fin de validite incorrecte',
		9 => 'Erreur verification comportementale',
		10 => 'Devise inconnue',
		11 => 'Montant incorrect',
		15 => 'Paiement deja effectue',
		16 => 'Abonne deja existant',
		21 => 'Carte non autorisee',
		29 => 'Carte non conforme',
		30 => 'Temps d\'attente superieur a 15min',
		102 => 'Contacter l\'emetteur de la carte',
		103 => 'Commercant invalide',
		104 => 'Conserver la carte',
		105 => 'Ne pas honorer',
		107 => 'Conserver la carte, conditions speciales',
		108 => 'Approuver apres identification du porteur',
		112 => 'Transaction invalide',
		113 => 'Montant invalide',
		114 => 'Numero du porteur invalide',
		115 => 'Emetteur de carte inconnu',
		117 => 'Annulation client',
		119 => 'Repeter la transaction ulterieurement',
		120 => 'Reponse erronee (erreur dans le domaine serveur)',
		124 => 'mise a jour de fichier non supportee',
		125 => 'impossible de localiser l\'enregistrement dans le fichier',
		126 => 'enregistrement duplique, ancien enregistrement remplace',
		127 => 'erreur en edit sur champ de mise a jour fichier',
		128 => 'acces interdit au fichier',
		129 => 'mise a jour de fichier impossible',
		130 => 'erreur de format',
		131 => 'identifiant de l\'organisme acqereur inconnu',
		133 => 'date de validite de la carte depassee',
		134 => 'Suspicion de fraude',
		138 => 'Nombre d\'essais code condifentiel depasse',
		141 => 'Carte perdue',
		143 => 'Carte volee',
		151 => 'Provison insuffisante ou credit depasse',
		154 => 'Date de validite de la carte depassee',
		155 => 'Code confidentiel errone',
		156 => 'Carte absente du fichier',
		157 => 'Transaction non permise a ce porteur',
		158 => 'Transaction interdite au terminal',
		159 => 'Suspicion de fraude',
		160 => 'L\'accepteur de carte dout contacter l\'acquereur',
		161 => 'Depasse la limite du montant de retrait',
		163 => 'Regles de securite non respectees',
		168 => 'Reponse non parvenue ou recue trop tard',
		175 => 'Nombre d\'essais code condidentiel depasse',
		176 => 'Porteur deja en opposition, ancien enregistrement conserve',
		190 => 'Arret momentane du systeme',
		191 => 'Emetteur de cartes inaccessible',
		194 => 'Demande dupliquee',
		196 => 'Mauvais fonctionnement du systeme',
		197 => 'Echeance de la temporisation de surveillance globale',
		198 => 'Serveur inacessible (positionne par le serveur)',
		199 => 'Incident domaine initiateur',

		'001A1' => 'Replis VADS (Soft Decline)',
		'001R1' => 'Révocation paiement récurrent pour la carte chez le commerçant ou pour le MCC (Merchant Category Code) et la carte',
		'001R3' => 'Révocation tous paiements récurrents pour la carte',
		'001A4' => 'Utilisation incorrecte du TRA (Transaction Risk Analysis)',
	);
	if (!is_numeric($code)) {
		if (isset($codes[$code])){
			return $pre . $codes[$code];
		}
	}
	elseif (isset($codes[intval($code)])){
		return $pre . $codes[intval($code)];
	}
	return $pre ? $pre : false;
}
