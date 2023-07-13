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

include_spip('inc/bank_devises');

/**
 * Retourner la liste des prestataires connus
 */
function bank_lister_prestas(){
	static $prestas = null;
	if (is_array($prestas)){
		return $prestas;
	}

	$prestas = array();
	$regexp = "(abonnement|acte)\.php$";
	foreach (creer_chemin() as $d){
		$f = $d . "presta/";
		if (@is_dir($f)){
			$all = preg_files($f, $regexp);
			foreach ($all as $a){
				$a = explode("/presta/", $a);
				$a = end($a);
				$a = explode("/", $a);
				if (count($a)==3 AND $a[1] = "payer"){
					$prestas[reset($a)] = true;
				}
			}
		}
	}
	ksort($prestas);
	// a la fin
	foreach (array("cheque", "virement", "simu") as $m){
		if (isset($prestas[$m])){
			unset($prestas[$m]);
			$prestas[$m] = true;
		}
	}
	if (isset($prestas['gratuit'])){
		unset($prestas['gratuit']);
	}

	$prestas = array_keys($prestas);
	return $prestas;
}

/**
 * Generer les urls de retour bank
 * @param array $config
 * @param string $action
 *   response|cancel|autoresponse
 * @param string $args
 *   query string
 * @return mixed|string
 */
function bank_url_api_retour($config, $action, $args = ""){
	static $is_api = null;
	if (is_null($is_api)){
		$is_api = false;
		if (file_exists($f = _DIR_RACINE . ".htaccess")){
			lire_fichier($f, $contenu);
			if (($p = strpos($contenu, 'spip.php?action=api_$'))!==false){
				$p_ligne = strrpos(substr($contenu, 0, $p), "\n");
				$ligne = substr($contenu, $p_ligne, $p-$p_ligne);
				$ligne = ltrim($ligne);
				if ($ligne[0]!=="#" and strpos($ligne, "RewriteRule") === 0){
					$is_api = true;
				}
			}
		}
	}

	$presta = $config['presta'] . "-" . bank_config_id($config);
	if ($is_api){
		return generer_url_public('', $args, false, false, "bank.api/$presta/$action/");
	} else {
		$args = (strlen($args) ? "&" : "") . $args;
		$args = "bankp=" . $presta . $args;
		return generer_url_action('bank_' . $action, $args, true, true);
	}
}


/**
 * Trouver une config d'apres le nom du presta
 * si un ID de config est fourni en suffixe du nom du presta,
 * on l'utilise pour verifier qu'on a bien la bonne config
 * (cas de configs multiples avec le meme presta)
 * sinon on prend la premiere config qui a le bon presta
 * Si possible on choisit une config "active"
 * mais si on ne trouve qu'une config non active on s'en contente
 *
 * @param string $presta
 *   soit presta-ID pour trouver le bon presta a coup sur
 *   soit presta tout seul (legacy) => trouvera le premier qui correspond
 * @param bool $abo
 * @return array
 */
function bank_config($presta, $abo = false){

	$id = "";
	$mode = $presta;
	if (preg_match(",[/-][A-F0-9]{4},Uims", $presta)){
		$mode = substr($presta, 0, -5);
		$id = substr($presta, -4);
	}
	if (substr($mode, -5)==="_test"){
		$mode = substr($mode, 0, -5);
	}

	// renommage d'un prestataire : assurer la continuite de fonctionnement
	if ($mode=="cyberplus"){
		$mode = "systempay";
	}
	$type = null;
	if ($abo){
		$type = 'abo';
	}

	$config = false;
	if ($mode!=="gratuit"){
		$configs = bank_lister_configs($type);
		$ids = array($id);
		if ($id){
			$ids[] = "";
		}
		foreach ($ids as $i){
			foreach ($configs as $k => $c){
				if ($c['presta']==$mode
					AND (!$i OR $i==bank_config_id($c))){
					// si actif c'est le bon, on sort
					if (isset($c['actif']) AND $c['actif']){
						$config = $c;
						break;
					}
					// si inactif on le memorise mais on continue a chercher
					if (!$config){
						$config = $c;
					}
				}
			}
			if ($config){
				break;
			}
		}

		if (!$config){
			spip_log("Configuration $mode introuvable", "bank" . _LOG_ERREUR);
			$config = array('erreur' => 'inconnu');
		}
	} // gratuit est un cas particulier
	else {
		$config = array(
			'presta' => 'gratuit',
			'actif' => true,
		);
	}

	#if (!isset($config['actif']) OR !$config['actif']){
	#	$config = array();
	#}

	if (!isset($config['presta'])){
		$config['presta'] = $mode; // servira pour l'aiguillage dans le futur
	}
	if (!isset($config['config'])){
		$config['config'] = ($abo ? 'abo_' : '') . $mode;
	}
	if (!isset($config['type'])){
		$config['type'] = ($abo ? 'abo' : 'acte');
	}

	return $config;
}

/**
 * Calculer un ID de config unique (qui change si un ID significatif change)
 * pour differencier 2 config du meme prestataire
 * @param array $config
 * @return string
 */
function bank_config_id($config){
	static $ids;
	$hash = serialize($config);
	if (isset($ids[$hash])){
		return $ids[$hash];
	}

	$presta = $config['presta'];
	if (include_spip("presta/{$presta}/inc/$presta") and function_exists($f = "{$presta}_list_keys_for_id")) {
		$keys = $f();
		$t = [
			'presta' => $presta
		];
		foreach ($keys as $k) {
			if (substr($k,-1) !== '_' or !empty($config[$k])) {
				$t[$k] = $config[$k];
			}
		}
	} else {
		// sinon choix des cles par defaut
		$t = $config;
		// enlever les cles non significatives
		foreach (array(
			         'actif',
			         'config',
			         'type',
			         'cartes',
			         'mode_test',
			         'label',
		         ) as $k){
			if (isset($t[$k])){
				unset($t[$k]);
			}
		}
		foreach ($t as $k => $v){
			if (
				// enlever les key/secret/signature/certificat
				stripos($k, "key")!==false
				OR stripos($k, "cle")!==false
				OR stripos($k, "secret")!==false
				OR stripos($k, "signature")!==false
				OR stripos($k, "certificat")!==false
				OR stripos($k, "password")!==false
				OR stripos($k, "token")!==false
				// enlever les logo/advert/notice/adresse
				OR stripos($k, "logo")!==false
				OR stripos($k, "advert")!==false
				OR stripos($k, "notice")!==false
				OR stripos($k, "adresse")!==false
			){
				unset($t[$k]);
			}
			elseif (substr($k,-1) === '_' and empty($v)){
				unset($t[$k]);
			}

		}
	}
	ksort($t);
	include_spip('inc/json');
	$t = json_encode($t);
	#var_dump($config['presta'],$t);
	return $ids[$hash] = strtoupper(substr(md5($t), 0, 4));
}


function bank_lister_configs($type = null){
	if ($type AND !in_array($type, array('abo', 'acte'))){
		$type = null;
	}

	include_spip('inc/config');
	$config = lire_config("bank_paiement/", array());
	$configs = array();
	if (is_array($config)){
		foreach ($config as $k => $v){
			if (strncmp($k, "config_", 7)==0){
				if (!$type OR ($v['type']==$type) OR $v['type']=='abo_acte'){
					$configs[substr($k, 7)] = $v;
				}
			}
		}
	}
	return $configs;
}

/**
 * Transformer un tableau d'argument en liste arg=value pour le shell
 * (en echappant de maniere securisee)
 * @param $params
 * @return string
 */
function bank_shell_args($params){
	$res = "";
	if ($params AND is_array($params)){
		foreach ($params as $k => $v){
			$res .= " " . escapeshellcmd($k) . "=" . escapeshellcmd($v);
		}
	}
	return $res;
}

/**
 * Generer un numero de transaction sur 6 chiffres unique pour une journee
 * Utilise par systempay et sips
 * pour le generer on utilise
 * le nombre de secondes depuis le debut de la journee x 10 + id_transaction%10
 * soit 864009
 * ce qui autorise 10 paiements/secondes. Au pire si collision il suffit de recommencer
 * deux presentations de la meme transaction donnent 2 vads_trans_id differents
 *
 * @param array $row
 * @return string
 */
function bank_transaction_id($row){
	$now = time();
	$id = 10*(date('s', $now)+60*(date('i', $now)+60*date('H', $now)));
	$id += modulo($row['id_transaction'], 10);
	return str_pad($id, 6, "0", STR_PAD_LEFT);
}


/**
 * Nom du site nettoye : pas de balises html ni de retour ligne ni d'entites html
 * et en utf8
 *
 * @return mixed
 */
function bank_nom_site(){
	if (!function_exists('textebrut')){
		include_spip('inc/filtres');
	}
	if (!function_exists('html2unicode')){
		include_spip('inc/charsets');
	}
	$nom_site = textebrut($GLOBALS['meta']['nom_site']);
	$nom_site = html2unicode($nom_site);
	$nom_site = unicode2charset($nom_site, 'utf-8');
	return str_replace(array("\r\n", "\r", "\n"), ' ', $nom_site);
}

/**
 * Recuperer l'email du porteur de la transaction (ou celui de la session a defaut ?)
 * @param array $transaction
 * @return string
 */
function bank_porteur_email($transaction){
	$mail = '';

	// recuperer l'email
	if (!$transaction['id_auteur']
		OR !$mail = sql_getfetsel('email', 'spip_auteurs', 'id_auteur=' . intval($transaction['id_auteur']))){

		if (strpos($transaction['auteur'], "@")!==false
			AND include_spip('inc/filtres')
			AND email_valide($transaction['auteur'])){
			$mail = $transaction['auteur'];
		} elseif (
			(!isset($GLOBALS['visiteur_session']['id_auteur']) OR $GLOBALS['visiteur_session']['id_auteur']==$transaction['id_auteur'])
			AND isset($GLOBALS['visiteur_session']['session_email'])
			AND $GLOBALS['visiteur_session']['session_email']) {
			$mail = $GLOBALS['visiteur_session']['session_email'];
		}
	}

	// fallback : utiliser l'email du webmetre du site pour permettre le paiement coute que coute
	if (!$mail){
		$mail = $GLOBALS['meta']['email_webmaster'];
	}

	return trim($mail);
}

/**
 * Recuperer le nom du porteur de la transaction (ou celui de la session a defaut ?)
 * @param array $transaction
 * @return string
 */
function bank_porteur_nom($transaction){
	$nom = '';

	// si prenom et nom en session on les utilise
	if (
		(!isset($GLOBALS['visiteur_session']['id_auteur']) OR $GLOBALS['visiteur_session']['id_auteur']==$transaction['id_auteur'])
		AND isset($GLOBALS['visiteur_session']['session_prenom'])
		AND isset($GLOBALS['visiteur_session']['session_nom'])){
		$nom = $GLOBALS['visiteur_session']['session_nom'];
	} // recuperer le nom
	elseif (!$transaction['id_auteur']
		OR !$nom = sql_getfetsel('nom', 'spip_auteurs', 'id_auteur=' . intval($transaction['id_auteur']))) {

		if ($transaction['auteur'] AND strpos($transaction['auteur'], "@")===false){
			$nom = $transaction['auteur'];
		}
	}

	return $nom;
}

/**
 * Recuperer le prenom du porteur de la transaction (ou celui de la session a defaut ?)
 * @param array $transaction
 * @return string
 */
function bank_porteur_prenom($transaction){
	$prenom = '';

	// recuperer le prenom
	if (
		(!isset($GLOBALS['visiteur_session']['id_auteur']) OR $GLOBALS['visiteur_session']['id_auteur']==$transaction['id_auteur'])
		AND isset($GLOBALS['visiteur_session']['session_prenom'])
		AND $GLOBALS['visiteur_session']['session_prenom']){
		$prenom = $GLOBALS['visiteur_session']['session_prenom'];
	}

	return $prenom;
}


/**
 * Recuperer les informations de facturation liees a la transaction
 * pour alimenter les infos DSP2 de demande de paiement
 * @param array $transaction
 * @return array
 */
function bank_porteur_infos_facturation($transaction){

	$infos = [
		'nom' => '',
		'prenom' => '',
		'email' => '',
		'adresse' => '',
		'code_postal' => '',
		'ville' => '',
		'etat' => '',
		'pays' => '',
	];

	$flux = [
		'args' => $transaction,
		'data' => $infos,
	];

	$infos = pipeline('bank_dsp2_renseigner_facturation', $flux);
	if (!$infos['email'] and $email = bank_porteur_email($transaction)){
		$infos['email'] = $email;
	}
	if (!$infos['prenom'] and $prenom = bank_porteur_prenom($transaction)){
		$infos['prenom'] = $prenom;
	}
	if (!$infos['nom'] and $nom = bank_porteur_nom($transaction)){
		$infos['nom'] = $nom;
	}

	// un peu de (re)mise en forme
	$infos = array_map('trim', $infos);

	$lignes = explode("\n", $infos['adresse']);
	$lignes = array_map('trim', $lignes);
	$lignes = array_filter($lignes);
	$infos['adresse'] = implode("\n", $lignes);

	#spip_log("bank_porteur_infos_facturation:".$transaction['id_transaction'].":".json_encode($infos),'dspdbg');

	return $infos;
}


/**
 * Libeller une transaction :
 * utile en amont pour la demande de paiement pour certains prestas (stripe)
 * et en aval pour libelle le paiement dans le releve (stripe)
 * @param int $id_transaction
 * @param array|null $transaction
 * @return array
 */
function bank_description_transaction($id_transaction, $transaction = null){
	$description = [
		'libelle' => '',
		'description' => _T('bank:titre_transaction') . " #$id_transaction",
	];

	if (is_null($transaction)){
		$transaction = sql_fetsel('*', 'spip_transactions', 'id_transaction=' . intval($id_transaction));
	}
	if ($transaction){

		/**
		 * Devrait etre dans les plugins concernes, compat anciennes versions
		 */
		if ($id_facture = $transaction['id_facture']
			and test_plugin_actif('factures')
			and $ref = sql_getfetsel('no_comptable', 'spip_factures', 'id_facture=' . intval($id_facture))){
			$description['libelle'] = _T('factures:titre_facture') . " $ref";
		} elseif ($id_commande = $transaction['id_commande']
			and test_plugin_actif('commande')
			and $ref = sql_getfetsel('reference', 'spip_commandes', 'id_commande=' . intval($id_commande))) {
			$description['libelle'] = _T('commande:commande_reference_numero') . " $ref";
		}

		$flux = [
			'args' => $transaction,
			'data' => $description,
		];

		$description = pipeline('bank_description_transaction', $flux);

	}

	if (!$description['libelle'] and $description['description']){
		$description['libelle'] = $description['description'];
		$description['description'] = '';
	}

	return $description;
}


/**
 * Calculer la date de fin de validite d'un moyen de paiement avec son annee/mois de validite
 * @param string $annee
 * @param string $mois
 * @return string
 */
function bank_date_fin_mois($annee, $mois){
	$date_fin = mktime(0, 0, 0, $mois, 01, $annee);
	$date_fin = strtotime("+1 month", $date_fin);
	return date('Y-m-d H:i:s', $date_fin);
}

/**
 * Generer le message d'erreur d'une transaction invalide/incoherente
 * avec log et email eventuel
 *
 * @param int|string $id_transaction
 * @param array $args
 *   string mode : mode de paiement
 *   string erreur :  texte en clair de l'erreur
 *   string log : texte complementaire pour les logs
 *   bool send_mail : avertir le webmestre par mail
 *   string sujet : sujet du mail
 *   bool update : mettre a jour la transaction en base ou non (false par defaut)
 * @return array
 */
function bank_transaction_invalide($id_transaction = "", $args = array()){

	$default = array(
		'mode' => 'defaut',
		'erreur' => '',
		'log' => '',
		'send_mail' => true,
		'sujet' => 'Transaction Invalide/Frauduleuse',
		'update' => false,
		'where' => 'call_response',
	);
	$args = array_merge($default, $args);
	$logname = str_replace(array('1', '2', '3', '4', '5', '6', '7', '8', '9'), array('un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'), $args['mode']);

	spip_log($t = $args['where'] . " : " . $args['sujet'] . " #$id_transaction (" . $args['erreur'] . ") " . $args['log'], $logname . _LOG_ERREUR);
	spip_log($t, $logname . "_invalides" . _LOG_ERREUR);

	if ($args['send_mail']){
		// avertir le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'], "[" . $args['mode'] . "] " . $args['sujet'], $t);
	}

	if (intval($id_transaction) AND $args['update']){
		$message = _T("bank:erreur_transaction_echec", array("ref" => "#$id_transaction"));
		$message .= "<br />" . _T('bank:erreur_transaction_invalide');
		$set = array(
			"mode" => $args['mode'],
			"statut" => 'echec[invalide]',
			"date_paiement" => date('Y-m-d H:i:s'),
			"erreur" => $args['erreur'],
			"message" => $message,
		);
		// verifier que le champ erreur existe pour ne pas risquer de planter l'enregistrement si l'up de base n'a pas encore ete fait
		if ($row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction))
			AND !isset($row['erreur'])){
			unset($set['erreur']);
		}
		sql_updateq("spip_transactions", $set, "id_transaction=" . intval($id_transaction));

		return array(intval($id_transaction), false);
	}

	return array(0, false);
}


/**
 * Generer le message d'erreur et l'enregistrement en base d'une transaction echouee
 * avec log et email eventuel
 *
 * @param int $id_transaction
 * @param array $args
 *   string mode : mode de paiement
 *   string date_paiement : date du paiement
 *   string code_erreur : code erreur
 *   string erreur :  texte en clair de l'erreur
 *   string log : texte complementaire pour les logs
 *   bool send_mail : avertir le webmestre par mail
 * @return array
 */
function bank_transaction_echec($id_transaction, $args = array()){

	$default = array(
		'mode' => 'defaut',
		'date_paiement' => date('Y-m-d H:i:s'),
		'code_erreur' => '',
		'erreur' => '',
		'log' => '',
		'send_mail' => false,
		'reglee' => 'non',
		'where' => 'call_response',
	);
	$args = array_merge($default, $args);
	$logname = str_replace(array('1', '2', '3', '4', '5', '6', '7', '8', '9'), array('un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'), $args['mode']);

	spip_log($t = $args['where'] . " : transaction $id_transaction refusee ou annulee pour : " . $args['code_erreur'] . " (" . $args['erreur'] . ") " . $args['log'], $logname . _LOG_ERREUR);
	$set = array(
		"mode" => $args['mode'] . (isset($args['config_id']) ? '/' . $args['config_id'] : ''),
		"statut" => 'echec' . ($args['code_erreur'] ? '[' . $args['code_erreur'] . ']' : ''),
		"date_paiement" => $args['date_paiement'],
		"erreur" => $args['erreur'],
		"message" => _T("bank:erreur_transaction_echec", array("ref" => "#$id_transaction")),
	);

	if (!empty($args['set'])) {
		$set = array_merge($args['set'], $set);
	}

	// verifier que le champ erreur existe pour ne pas risquer de planter l'enregistrement si l'up de base n'a pas encore ete fait
	if ($row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction))
		AND !isset($row['erreur'])){
		unset($set['erreur']);
	}

	sql_updateq("spip_transactions", $set, "id_transaction=" . intval($id_transaction));

	if ($args['send_mail']){
		// avertir le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'], "[" . $args['mode'] . "] Transaction Impossible", $t);
	}
	return array($id_transaction, false);
}

/*
 * Modes de paiement simples (cheque, virement)
 */


/**
 * Recuperer la reponse postee et verifier sa signature
 * @param string $mode
 * @param array|null $mode
 * @return array|bool
 */
function bank_response_simple($mode, $c = null){
	$vars = array('id_transaction', 'transaction_hash', 'autorisation_id', 'abo', 'montant');
	$response = array();
	foreach ($vars as $k){
		if (!is_null($v = _request($k, $c))){
			$response[$k] = $v;
		}
	}

	if (!$s = _request('sign', $c)
		OR $s!==bank_sign_response_simple($mode, $response)){

		spip_log("bank_response_simple : signature invalide", "bank" . _LOG_ERREUR);
		return false;
	}
	return $response;
}

/**
 * Calculer une signature de la reponse (securite)
 * @param string $mode
 * @param array $response
 * @return string
 */
function bank_sign_response_simple($mode, $response = array()){
	ksort($response);
	foreach ($response as $k => $v){
		if (is_numeric($v)){
			$response[$k] = (string)$v;
		}
	}
	$s = serialize($response);
	include_spip("inc/securiser_action");
	$sign = calculer_cle_action("bank-$mode-$s");
	return $sign;
}

/**
 * Call response simple (cheque, virement, simu)
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function bank_simple_call_response($config, $response = null){

	$mode = $config['presta'];
	$config_id = bank_config_id($config);

	// recuperer la reponse en post et la decoder, en verifiant la signature
	if (!$response){
		$response = bank_response_simple($mode);
	}

	if (!isset($response['id_transaction']) OR !isset($response['transaction_hash'])){
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => var_export($response, true),
			)
		);
	}

	$id_transaction = $response['id_transaction'];
	$transaction_hash = $response['transaction_hash'];

	if (!$row = sql_fetsel('*', 'spip_transactions', 'id_transaction=' . intval($id_transaction))){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction non trouvee",
				'log' => var_export($response, true),
			)
		);
	}
	if ($transaction_hash!=$row['transaction_hash']){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "hash $transaction_hash non conforme",
				'log' => var_export($response, true),
			)
		);
	}

	$autorisation = (isset($response['autorisation_id']) ? $response['autorisation_id'] : '');
	if ($autorisation==="wait"){

		// c'est un reglement en attente, on le note
		$set = array(
			"mode" => "$mode/$config_id",
			'autorisation_id' => date('d/m/Y-H:i:s') . "/" . $GLOBALS['ip'],
			"date_paiement" => date('Y-m-d H:i:s'),
			"statut" => 'attente',
		);

	} else {
		// si rien fourni l'autorisation refere l'id_auteur et le nom de celui qui accepte le cheque|virement
		if (!$autorisation){
			if (isset($GLOBALS['visiteur_session']['id_auteur']) and $GLOBALS['visiteur_session']['id_auteur']){
				$autorisation = $GLOBALS['visiteur_session']['id_auteur'] . "/" . $GLOBALS['visiteur_session']['nom'];
			} else {
				$autorisation = $GLOBALS['ip'] . "/" . date('d/m/Y-H:i:s');
			}
		}

		include_spip("inc/autoriser");
		if (!autoriser('utilisermodepaiement', $mode)){
			return bank_transaction_invalide($id_transaction,
				array(
					'mode' => $mode,
					'erreur' => "$mode pas autorisee",
				)
			);
		}

		if (!autoriser('encaisser' . $mode, 'transaction', $id_transaction)){
			return bank_transaction_invalide($id_transaction,
				array(
					'mode' => $mode,
					'erreur' => "tentative d'encaisser un $mode par auteur #$autorisation pas autorise",
				)
			);
		}

		// est-ce une demande d'echec ? (cas de la simulation)
		if (isset($response['fail']) AND $response['fail']){
			// sinon enregistrer l'absence de paiement et l'erreur
			include_spip('inc/bank');
			return bank_transaction_echec($id_transaction,
				array(
					'mode' => $mode,
					'config_id' => $config_id,
					'code_erreur' => 'fail',
					'erreur' => $response['fail'],
				)
			);
		}

		// OK, on peut accepter le reglement
		$montant_regle = $row['montant'];
		if (isset($response['montant'])){
			$montant_regle = $response['montant'];
		}
		if ($montant_regle!=$row['montant']){
			spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=" . $row['montant'] . ":" . bank_shell_args($response), $mode);
			// on log ca dans un journal dedie
			spip_log($t, $mode . '_reglements_partiels');
		}

		$set = array(
			"mode" => "$mode/$config_id",
			"autorisation_id" => $autorisation,
			"montant_regle" => $montant_regle,
			"date_paiement" => date('Y-m-d H:i:s'),
			"statut" => 'ok',
			"reglee" => 'oui'
		);

	}


	// est-ce un abonnement ?
	if (isset($response['abo_uid']) AND $response['abo_uid']){
		$set['abo_uid'] = $response['abo_uid'];
		if (isset($response['pay_id']) AND $response['pay_id']){
			$set['pay_id'] = $response['pay_id'];
		}
		if (isset($response['auteur_id']) AND $response['auteur_id']){
			$set['auteur_id'] = $response['auteur_id'];
		}
	}

	sql_updateq("spip_transactions", $set, "id_transaction=" . intval($id_transaction));

	// si ok on regle
	if ($set['statut']==='ok'){
		spip_log("call_resonse : id_transaction $id_transaction, reglee", $mode);

		$options = array('row_prec' => $row);
		// si l'auteur connecte est celui de la transaction, on garde la langue courante
		// (sinon ca prendra automatiquement celle de l'auteur)
		if (intval($row['id_auteur'])
			and isset($GLOBALS['visiteur_session']['id_auteur'])
			and intval($GLOBALS['visiteur_session']['id_auteur']) === intval($row['id_auteur']) ) {
			$options['lang'] = $GLOBALS['spip_lang'];
		}

		$regler_transaction = charger_fonction('regler_transaction', 'bank');
		$regler_transaction($id_transaction, $options);

		$res = true;
	} // sinon on trig les reglements en attente
	else {
		// cela permet de factoriser le code
		$row = sql_fetsel('*', 'spip_transactions', 'id_transaction=' . intval($id_transaction));
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

	return array($id_transaction, $res);
}


/**
 * Jamais appele directement dans le plugin bank/
 * mais par une eventuelle methode abos/resilier d'un plugin externe
 * c'est une fallback quand le presta ne sait pas gerer le desabonnement en appel serveur
 * ou qu'il repose sur bank_recurrences
 * ou quand cela echoue
 *
 * @param string $uid
 * @param array|string $config
 * @return bool
 */
function bank_simple_call_resilier_abonnement($uid, $config){

	include_spip('inc/bank');
	if (!is_array($config)){
		$mode = sql_getfetsel("mode", "spip_transactions", "abo_uid=" . sql_quote($uid) . " AND statut=" . sql_quote('ok') . " AND mode LIKE " . sql_quote($config . '%'));
		$config = bank_config($mode);
	}

	// tenter avec la gestion des recurrences internes au plugin
	include_spip('inc/bank_recurrences');
	$ok = bank_recurrence_terminer($uid, 'fini', false);

	if (!$ok) {
		// on envoie un mail au webmestre avec reference pour que le webmestre aille faire la resiliation manuellement
		$sujet = "[" . $GLOBALS['meta']['nom_site'] . "] Demande Resiliation Abonnement " . $config['presta'];
		$message = "Abonne UID : $uid\nTransactions :\n";


		$trans = sql_allfetsel("id_transaction,date_paiement,montant,devise", "spip_transactions", "abo_uid=" . sql_quote($uid) . " AND statut=" . sql_quote('ok') . " AND mode LIKE " . sql_quote($config['presta'] . '%'));
		foreach ($trans as $tran){
			$message .= "#" . $tran['id_transaction'] . " " . $tran['date_paiement'] . " " . bank_affiche_montant($tran['montant'],$tran['devise'], true, true) . "\n";
		}

		$envoyer_mail = charger_fonction("envoyer_mail", "inc");
		$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $message);
	}

	return $ok;
}


/**
 * Trouver un logo pour un presta donne
 * Historiquement les logos etaient des .gif, possiblement specifique aux prestas
 * On peut les surcharger par un .png (ou un .svg a partir de SPIP 3.2.5)
 * @param $mode
 * @param $logo
 * @return bool|string
 */
function bank_trouver_logo($mode, $logo){
	static $svg_allowed;
	if (is_null($svg_allowed)){
		$svg_allowed = false;
		// _SPIP_VERSION_ID definie en 3.3 et 3.2.5-dev
		if (defined('_SPIP_VERSION_ID') and _SPIP_VERSION_ID>=30205){
			$svg_allowed = true;
		} else {
			$branche = explode('.', $GLOBALS['spip_version_branche']);
			if ($branche[0]==3 and $branche[1]==2 and $branche[2]>=5){
				$svg_allowed = true;
			}
		}
	}

	if (substr($logo, -4)=='.gif'
		and $f = bank_trouver_logo($mode, substr(strtolower($logo), 0, -4) . ".png")){
		return $f;
	}
	if ($svg_allowed
		and substr($logo, -4)=='.png'
		and $f = bank_trouver_logo($mode, substr(strtolower($logo), 0, -4) . ".svg")){
		return $f;
	}

	// d'abord dans un dossier presta/
	if ($f = find_in_path("presta/$mode/logo/$logo")){
		return $f;
	} // sinon le dossier generique
	elseif ($f = find_in_path("bank/logo/$logo")) {
		return $f;
	}
	return "";
}


/**
 * Annoncer SPIP + plugin&version pour les logs de certains providers
 * @param string $format
 * @return string
 */
function bank_annonce_version_plugin($format = 'string'){
	$infos = array(
		'name' => 'SPIP ' . $GLOBALS['spip_version_branche'] . ' + Bank',
		'url' => 'https://github.com/nursit/bank',
		'version' => '',
	);
	include_spip('inc/filtres');
	if ($info_plugin = chercher_filtre("info_plugin")){
		$infos['version'] = 'v' . $info_plugin("bank", "version");
	}

	if ($format==='string'){
		return $infos['name'] . $infos['version'] . '(' . $infos['url'] . ')';
	}

	return $infos;
}



/**
 * Calcul du nom du jeton checkout
 * Permet de gerer/eviter un double hit au moment de faire un appel au presta paiement pour preparer un paiement
 * @param string $mode
 * @param int $id_transaction
 * @return string
 */
function bank_lock_checkout_token($mode, $id_transaction) {
	return sous_repertoire(_DIR_TMP, 'bank') . $mode . "_checkout_" . strval($id_transaction) . ".lock";
}

/**
 * Gestion du jeton checkout
 *
 * Le premier appel
 * `$contexte = bank_lock_or_get_checkout($mode, $jeton);`
 * permet de poser le jeton si pas de concurrence (retourne une valeur null),
 * ou d'attendre que le process concurrent ait fini la même operation
 * et dans ce cas retourne le `$contexte` obtenu par le process concurrent
 *
 * Si le premier appel renvoie une valeur null (on est le premier)
 * le second appel permet de stocker le résultat ou de libérer le jeton en cas d'échec (si `$contexte===false`) :
 * `bank_lock_or_get_checkout($mode, $jeton, $contexte);`
 *
 *
 * @see bank_lock_checkout_token()
 * @param string $mode
 * @param string $jeton
 *   jeton précédemment calculé par la fonction `bank_lock_checkout_token()`
 * @param array|null|false $contexte
 *   null : premier appel pour poser le lock ou attendre le résultat du process concurrent déjà en cours
 *   array : stocker le résultat dans le jeton
 *   false : echec de l'opération, on libère le lock
 * @return array|null|false
 */
function bank_lock_or_get_checkout($mode, $jeton, $contexte = null) {
	// le call request a echoué, libérer le jeton
	if ($contexte === false) {
		@unlink($jeton);
		return false;
	}
	// le call request a reussi, stocker son résultat dans le jeton pour un éventuel hit concurrent qui attends
	if (is_array($contexte)) {
		file_put_contents($jeton, json_encode($contexte));

		// nettoyer les vieux jetons
		$oldies = glob(_DIR_TMP . "bank/" . $mode . "_checkout_*.lock");
		foreach ($oldies as $old) {
			if (filemtime($old) < $_SERVER['REQUEST_TIME'] - 86400) {
				@unlink($old);
			}
		}
		return $contexte;
	}
	// appel initial, on pose le jeton si il n'existe pas, sinon on attends son résultat (10s maxi)
	if (is_null($contexte)) {
		// anti double action : si on a deja un lock sur cette transaction datant de moins de 10s
		// on attends le resultat fourni par le process concurrent
		// sinon au bout de 10s on continue notre hit, tant pis
		$jeton_valid = 10; // 10 secondes
		if (file_exists($jeton) and filemtime($jeton) > $_SERVER['REQUEST_TIME'] - $jeton_valid) {
			do {
				spip_log("bank_lock_checkout() WAIT (double hit)", $mode . _LOG_INFO_IMPORTANTE);
				$c = file_get_contents($jeton);
				if ($c and $contexte = json_decode($c, true)) {
					return $contexte;
				}
				sleep(1);
			} while (time() < $_SERVER['REQUEST_TIME'] + $jeton_valid);

			spip_log("bank_lock_checkout WAIT FAIL", $mode . _LOG_INFO_IMPORTANTE);
		}
		else {
			@touch($jeton);
		}
	}
	return null;
}

/**
 * Fonction utile pour retourner un code_pays dans un des 3 formats iso
 * alpha-2, alpha-3 et numerique
 * https://www.atlas-monde.net/codes-iso/
 *
 * (le format en entrée est auto-détecté parmi ces 3 formats)
 * @param string $input_pays
 * @param string $format_code_sortie
 *   iso_alpha2, iso_alpha3 ou iso_num
 * @return string|int
 */
function bank_code_pays($input_pays, $format_code_sortie = 'iso_alpha3') {
	static $pays = null;

	// si on ne connait pas le format demandé en sortie, on ne fait rien
	if (!in_array($format_code_sortie, array('iso_alpha2', 'iso_alpha3', 'iso_num'))) {
		return $input_pays;
	}

	// detecter le format en entrée
	$format_input = 'iso_alpha2';
	if (is_numeric($input_pays)) {
		$format_input = 'iso_num';
	} elseif(strlen($input_pays) === 3) {
		$format_input = 'iso_alpha3';
	}

	if (is_null($pays)) {
		$pays = array(
			array('iso_alpha2'=>'AF','iso_num'=>4,'iso_alpha3'=>'AFG'),
			array('iso_alpha2'=>'ZA','iso_num'=>710,'iso_alpha3'=>'ZAF'),
			array('iso_alpha2'=>'AX','iso_num'=>248,'iso_alpha3'=>'ALA'),
			array('iso_alpha2'=>'AL','iso_num'=>8,'iso_alpha3'=>'ALB'),
			array('iso_alpha2'=>'DZ','iso_num'=>12,'iso_alpha3'=>'DZA'),
			array('iso_alpha2'=>'DE','iso_num'=>276,'iso_alpha3'=>'DEU'),
			array('iso_alpha2'=>'AD','iso_num'=>20,'iso_alpha3'=>'AND'),
			array('iso_alpha2'=>'AO','iso_num'=>24,'iso_alpha3'=>'AGO'),
			array('iso_alpha2'=>'AI','iso_num'=>660,'iso_alpha3'=>'AIA'),
			array('iso_alpha2'=>'AQ','iso_num'=>10,'iso_alpha3'=>'ATA'),
			array('iso_alpha2'=>'AG','iso_num'=>28,'iso_alpha3'=>'ATG'),
			array('iso_alpha2'=>'AN','iso_num'=>530,'iso_alpha3'=>'ANT'),
			array('iso_alpha2'=>'SA','iso_num'=>682,'iso_alpha3'=>'SAU'),
			array('iso_alpha2'=>'AR','iso_num'=>32,'iso_alpha3'=>'ARG'),
			array('iso_alpha2'=>'AM','iso_num'=>51,'iso_alpha3'=>'ARM'),
			array('iso_alpha2'=>'AW','iso_num'=>533,'iso_alpha3'=>'ABW'),
			array('iso_alpha2'=>'AU','iso_num'=>36,'iso_alpha3'=>'AUS'),
			array('iso_alpha2'=>'AT','iso_num'=>40,'iso_alpha3'=>'AUT'),
			array('iso_alpha2'=>'AZ','iso_num'=>31,'iso_alpha3'=>'AZE'),
			array('iso_alpha2'=>'BS','iso_num'=>44,'iso_alpha3'=>'BHS'),
			array('iso_alpha2'=>'BH','iso_num'=>48,'iso_alpha3'=>'BHR'),
			array('iso_alpha2'=>'BD','iso_num'=>50,'iso_alpha3'=>'BGD'),
			array('iso_alpha2'=>'BB','iso_num'=>52,'iso_alpha3'=>'BRB'),
			array('iso_alpha2'=>'BE','iso_num'=>56,'iso_alpha3'=>'BEL'),
			array('iso_alpha2'=>'BZ','iso_num'=>84,'iso_alpha3'=>'BLZ'),
			array('iso_alpha2'=>'BJ','iso_num'=>204,'iso_alpha3'=>'BEN'),
			array('iso_alpha2'=>'BM','iso_num'=>60,'iso_alpha3'=>'BMU'),
			array('iso_alpha2'=>'BT','iso_num'=>64,'iso_alpha3'=>'BTN'),
			array('iso_alpha2'=>'BY','iso_num'=>112,'iso_alpha3'=>'BLR'),
			array('iso_alpha2'=>'MM','iso_num'=>104,'iso_alpha3'=>'MMR'),
			array('iso_alpha2'=>'BO','iso_num'=>68,'iso_alpha3'=>'BOL'),
			array('iso_alpha2'=>'BA','iso_num'=>70,'iso_alpha3'=>'BIH'),
			array('iso_alpha2'=>'BW','iso_num'=>72,'iso_alpha3'=>'BWA'),
			array('iso_alpha2'=>'BR','iso_num'=>76,'iso_alpha3'=>'BRA'),
			array('iso_alpha2'=>'BN','iso_num'=>96,'iso_alpha3'=>'BRN'),
			array('iso_alpha2'=>'BG','iso_num'=>100,'iso_alpha3'=>'BGR'),
			array('iso_alpha2'=>'BF','iso_num'=>854,'iso_alpha3'=>'BFA'),
			array('iso_alpha2'=>'BI','iso_num'=>108,'iso_alpha3'=>'BDI'),
			array('iso_alpha2'=>'KH','iso_num'=>116,'iso_alpha3'=>'KHM'),
			array('iso_alpha2'=>'CM','iso_num'=>120,'iso_alpha3'=>'CMR'),
			array('iso_alpha2'=>'CA','iso_num'=>124,'iso_alpha3'=>'CAN'),
			array('iso_alpha2'=>'CV','iso_num'=>132,'iso_alpha3'=>'CPV'),
			array('iso_alpha2'=>'CF','iso_num'=>140,'iso_alpha3'=>'CAF'),
			array('iso_alpha2'=>'CL','iso_num'=>152,'iso_alpha3'=>'CHL'),
			array('iso_alpha2'=>'CN','iso_num'=>156,'iso_alpha3'=>'CHN'),
			array('iso_alpha2'=>'CY','iso_num'=>196,'iso_alpha3'=>'CYP'),
			array('iso_alpha2'=>'CO','iso_num'=>170,'iso_alpha3'=>'COL'),
			array('iso_alpha2'=>'KM','iso_num'=>174,'iso_alpha3'=>'COM'),
			array('iso_alpha2'=>'CG','iso_num'=>178,'iso_alpha3'=>'COG'),
			array('iso_alpha2'=>'CD','iso_num'=>180,'iso_alpha3'=>'COD'),
			array('iso_alpha2'=>'KP','iso_num'=>408,'iso_alpha3'=>'PRK'),
			array('iso_alpha2'=>'KR','iso_num'=>410,'iso_alpha3'=>'KOR'),
			array('iso_alpha2'=>'CR','iso_num'=>188,'iso_alpha3'=>'CRI'),
			array('iso_alpha2'=>'CI','iso_num'=>384,'iso_alpha3'=>'CIV'),
			array('iso_alpha2'=>'HR','iso_num'=>191,'iso_alpha3'=>'HRV'),
			array('iso_alpha2'=>'CU','iso_num'=>192,'iso_alpha3'=>'CUB'),
			array('iso_alpha2'=>'DK','iso_num'=>208,'iso_alpha3'=>'DNK'),
			array('iso_alpha2'=>'DJ','iso_num'=>262,'iso_alpha3'=>'DJI'),
			array('iso_alpha2'=>'DM','iso_num'=>212,'iso_alpha3'=>'DMA'),
			array('iso_alpha2'=>'EG','iso_num'=>818,'iso_alpha3'=>'EGY'),
			array('iso_alpha2'=>'AE','iso_num'=>784,'iso_alpha3'=>'ARE'),
			array('iso_alpha2'=>'EC','iso_num'=>218,'iso_alpha3'=>'ECU'),
			array('iso_alpha2'=>'ER','iso_num'=>232,'iso_alpha3'=>'ERI'),
			array('iso_alpha2'=>'ES','iso_num'=>724,'iso_alpha3'=>'ESP'),
			array('iso_alpha2'=>'EE','iso_num'=>233,'iso_alpha3'=>'EST'),
			array('iso_alpha2'=>'US','iso_num'=>840,'iso_alpha3'=>'USA'),
			array('iso_alpha2'=>'ET','iso_num'=>231,'iso_alpha3'=>'ETH'),
			array('iso_alpha2'=>'FJ','iso_num'=>242,'iso_alpha3'=>'FJI'),
			array('iso_alpha2'=>'FI','iso_num'=>246,'iso_alpha3'=>'FIN'),
			array('iso_alpha2'=>'FR','iso_num'=>250,'iso_alpha3'=>'FRA'),
			array('iso_alpha2'=>'GA','iso_num'=>266,'iso_alpha3'=>'GAB'),
			array('iso_alpha2'=>'GM','iso_num'=>270,'iso_alpha3'=>'GMB'),
			array('iso_alpha2'=>'GE','iso_num'=>268,'iso_alpha3'=>'GEO'),
			array('iso_alpha2'=>'GS','iso_num'=>239,'iso_alpha3'=>'SGS'),
			array('iso_alpha2'=>'GH','iso_num'=>288,'iso_alpha3'=>'GHA'),
			array('iso_alpha2'=>'GI','iso_num'=>292,'iso_alpha3'=>'GIB'),
			array('iso_alpha2'=>'GR','iso_num'=>300,'iso_alpha3'=>'GRC'),
			array('iso_alpha2'=>'GD','iso_num'=>308,'iso_alpha3'=>'GRD'),
			array('iso_alpha2'=>'GL','iso_num'=>304,'iso_alpha3'=>'GRL'),
			array('iso_alpha2'=>'GP','iso_num'=>312,'iso_alpha3'=>'GLP'),
			array('iso_alpha2'=>'GU','iso_num'=>316,'iso_alpha3'=>'GUM'),
			array('iso_alpha2'=>'GT','iso_num'=>320,'iso_alpha3'=>'GTM'),
			array('iso_alpha2'=>'GG','iso_num'=>831,'iso_alpha3'=>'GGY'),
			array('iso_alpha2'=>'GN','iso_num'=>324,'iso_alpha3'=>'GIN'),
			array('iso_alpha2'=>'GW','iso_num'=>624,'iso_alpha3'=>'GNB'),
			array('iso_alpha2'=>'GQ','iso_num'=>226,'iso_alpha3'=>'GNQ'),
			array('iso_alpha2'=>'GY','iso_num'=>328,'iso_alpha3'=>'GUY'),
			array('iso_alpha2'=>'GF','iso_num'=>254,'iso_alpha3'=>'GUF'),
			array('iso_alpha2'=>'HT','iso_num'=>332,'iso_alpha3'=>'HTI'),
			array('iso_alpha2'=>'HN','iso_num'=>340,'iso_alpha3'=>'HND'),
			array('iso_alpha2'=>'HK','iso_num'=>344,'iso_alpha3'=>'HKG'),
			array('iso_alpha2'=>'HU','iso_num'=>348,'iso_alpha3'=>'HUN'),
			array('iso_alpha2'=>'BV','iso_num'=>74,'iso_alpha3'=>'BVT'),
			array('iso_alpha2'=>'CX','iso_num'=>162,'iso_alpha3'=>'CXR'),
			array('iso_alpha2'=>'IM','iso_num'=>833,'iso_alpha3'=>'IMN'),
			array('iso_alpha2'=>'KY','iso_num'=>136,'iso_alpha3'=>'CYM'),
			array('iso_alpha2'=>'CC','iso_num'=>166,'iso_alpha3'=>'CCK'),
			array('iso_alpha2'=>'CK','iso_num'=>184,'iso_alpha3'=>'COK'),
			array('iso_alpha2'=>'FO','iso_num'=>234,'iso_alpha3'=>'FRO'),
			array('iso_alpha2'=>'FK','iso_num'=>238,'iso_alpha3'=>'FLK'),
			array('iso_alpha2'=>'MP','iso_num'=>580,'iso_alpha3'=>'MNP'),
			array('iso_alpha2'=>'UM','iso_num'=>581,'iso_alpha3'=>'UMI'),
			array('iso_alpha2'=>'SB','iso_num'=>90,'iso_alpha3'=>'SLB'),
			array('iso_alpha2'=>'TC','iso_num'=>796,'iso_alpha3'=>'TCA'),
			array('iso_alpha2'=>'VI','iso_num'=>850,'iso_alpha3'=>'VIR'),
			array('iso_alpha2'=>'VG','iso_num'=>92,'iso_alpha3'=>'VGB'),
			array('iso_alpha2'=>'IN','iso_num'=>356,'iso_alpha3'=>'IND'),
			array('iso_alpha2'=>'ID','iso_num'=>360,'iso_alpha3'=>'IDN'),
			array('iso_alpha2'=>'IQ','iso_num'=>368,'iso_alpha3'=>'IRQ'),
			array('iso_alpha2'=>'IR','iso_num'=>364,'iso_alpha3'=>'IRN'),
			array('iso_alpha2'=>'IE','iso_num'=>372,'iso_alpha3'=>'IRL'),
			array('iso_alpha2'=>'IS','iso_num'=>352,'iso_alpha3'=>'ISL'),
			array('iso_alpha2'=>'IL','iso_num'=>376,'iso_alpha3'=>'ISR'),
			array('iso_alpha2'=>'IT','iso_num'=>380,'iso_alpha3'=>'ITA'),
			array('iso_alpha2'=>'JM','iso_num'=>388,'iso_alpha3'=>'JAM'),
			array('iso_alpha2'=>'JP','iso_num'=>392,'iso_alpha3'=>'JPN'),
			array('iso_alpha2'=>'JE','iso_num'=>832,'iso_alpha3'=>'JEY'),
			array('iso_alpha2'=>'JO','iso_num'=>400,'iso_alpha3'=>'JOR'),
			array('iso_alpha2'=>'KZ','iso_num'=>398,'iso_alpha3'=>'KAZ'),
			array('iso_alpha2'=>'KE','iso_num'=>404,'iso_alpha3'=>'KEN'),
			array('iso_alpha2'=>'KG','iso_num'=>417,'iso_alpha3'=>'KGZ'),
			array('iso_alpha2'=>'KI','iso_num'=>296,'iso_alpha3'=>'KIR'),
			array('iso_alpha2'=>'KW','iso_num'=>414,'iso_alpha3'=>'KWT'),
			array('iso_alpha2'=>'LA','iso_num'=>418,'iso_alpha3'=>'LAO'),
			array('iso_alpha2'=>'LS','iso_num'=>426,'iso_alpha3'=>'LSO'),
			array('iso_alpha2'=>'LV','iso_num'=>428,'iso_alpha3'=>'LVA'),
			array('iso_alpha2'=>'LB','iso_num'=>422,'iso_alpha3'=>'LBN'),
			array('iso_alpha2'=>'LR','iso_num'=>430,'iso_alpha3'=>'LBR'),
			array('iso_alpha2'=>'LY','iso_num'=>434,'iso_alpha3'=>'LBY'),
			array('iso_alpha2'=>'LI','iso_num'=>438,'iso_alpha3'=>'LIE'),
			array('iso_alpha2'=>'LT','iso_num'=>440,'iso_alpha3'=>'LTU'),
			array('iso_alpha2'=>'LU','iso_num'=>442,'iso_alpha3'=>'LUX'),
			array('iso_alpha2'=>'MO','iso_num'=>446,'iso_alpha3'=>'MAC'),
			array('iso_alpha2'=>'MK','iso_num'=>807,'iso_alpha3'=>'MKD'),
			array('iso_alpha2'=>'MG','iso_num'=>450,'iso_alpha3'=>'MDG'),
			array('iso_alpha2'=>'MY','iso_num'=>458,'iso_alpha3'=>'MYS'),
			array('iso_alpha2'=>'MW','iso_num'=>454,'iso_alpha3'=>'MWI'),
			array('iso_alpha2'=>'MV','iso_num'=>462,'iso_alpha3'=>'MDV'),
			array('iso_alpha2'=>'ML','iso_num'=>466,'iso_alpha3'=>'MLI'),
			array('iso_alpha2'=>'MT','iso_num'=>470,'iso_alpha3'=>'MLT'),
			array('iso_alpha2'=>'MA','iso_num'=>504,'iso_alpha3'=>'MAR'),
			array('iso_alpha2'=>'MH','iso_num'=>584,'iso_alpha3'=>'MHL'),
			array('iso_alpha2'=>'MQ','iso_num'=>474,'iso_alpha3'=>'MTQ'),
			array('iso_alpha2'=>'MU','iso_num'=>480,'iso_alpha3'=>'MUS'),
			array('iso_alpha2'=>'MR','iso_num'=>478,'iso_alpha3'=>'MRT'),
			array('iso_alpha2'=>'YT','iso_num'=>175,'iso_alpha3'=>'MYT'),
			array('iso_alpha2'=>'MX','iso_num'=>484,'iso_alpha3'=>'MEX'),
			array('iso_alpha2'=>'FM','iso_num'=>583,'iso_alpha3'=>'FSM'),
			array('iso_alpha2'=>'MD','iso_num'=>498,'iso_alpha3'=>'MDA'),
			array('iso_alpha2'=>'MC','iso_num'=>492,'iso_alpha3'=>'MCO'),
			array('iso_alpha2'=>'MN','iso_num'=>496,'iso_alpha3'=>'MNG'),
			array('iso_alpha2'=>'ME','iso_num'=>499,'iso_alpha3'=>'MNE'),
			array('iso_alpha2'=>'MS','iso_num'=>500,'iso_alpha3'=>'MSR'),
			array('iso_alpha2'=>'MZ','iso_num'=>508,'iso_alpha3'=>'MOZ'),
			array('iso_alpha2'=>'NA','iso_num'=>516,'iso_alpha3'=>'NAM'),
			array('iso_alpha2'=>'NR','iso_num'=>520,'iso_alpha3'=>'NRU'),
			array('iso_alpha2'=>'NP','iso_num'=>524,'iso_alpha3'=>'NPL'),
			array('iso_alpha2'=>'NI','iso_num'=>558,'iso_alpha3'=>'NIC'),
			array('iso_alpha2'=>'NE','iso_num'=>562,'iso_alpha3'=>'NER'),
			array('iso_alpha2'=>'NG','iso_num'=>566,'iso_alpha3'=>'NGA'),
			array('iso_alpha2'=>'NU','iso_num'=>570,'iso_alpha3'=>'NIU'),
			array('iso_alpha2'=>'NF','iso_num'=>574,'iso_alpha3'=>'NFK'),
			array('iso_alpha2'=>'NO','iso_num'=>578,'iso_alpha3'=>'NOR'),
			array('iso_alpha2'=>'NC','iso_num'=>540,'iso_alpha3'=>'NCL'),
			array('iso_alpha2'=>'NZ','iso_num'=>554,'iso_alpha3'=>'NZL'),
			array('iso_alpha2'=>'OM','iso_num'=>512,'iso_alpha3'=>'OMN'),
			array('iso_alpha2'=>'UG','iso_num'=>800,'iso_alpha3'=>'UGA'),
			array('iso_alpha2'=>'UZ','iso_num'=>860,'iso_alpha3'=>'UZB'),
			array('iso_alpha2'=>'PK','iso_num'=>586,'iso_alpha3'=>'PAK'),
			array('iso_alpha2'=>'PW','iso_num'=>585,'iso_alpha3'=>'PLW'),
			array('iso_alpha2'=>'PS','iso_num'=>275,'iso_alpha3'=>'PSE'),
			array('iso_alpha2'=>'PA','iso_num'=>591,'iso_alpha3'=>'PAN'),
			array('iso_alpha2'=>'PG','iso_num'=>598,'iso_alpha3'=>'PNG'),
			array('iso_alpha2'=>'PY','iso_num'=>600,'iso_alpha3'=>'PRY'),
			array('iso_alpha2'=>'NL','iso_num'=>528,'iso_alpha3'=>'NLD'),
			array('iso_alpha2'=>'PE','iso_num'=>604,'iso_alpha3'=>'PER'),
			array('iso_alpha2'=>'PH','iso_num'=>608,'iso_alpha3'=>'PHL'),
			array('iso_alpha2'=>'PN','iso_num'=>612,'iso_alpha3'=>'PCN'),
			array('iso_alpha2'=>'PL','iso_num'=>616,'iso_alpha3'=>'POL'),
			array('iso_alpha2'=>'PF','iso_num'=>258,'iso_alpha3'=>'PYF'),
			array('iso_alpha2'=>'PR','iso_num'=>630,'iso_alpha3'=>'PRI'),
			array('iso_alpha2'=>'PT','iso_num'=>620,'iso_alpha3'=>'PRT'),
			array('iso_alpha2'=>'QA','iso_num'=>634,'iso_alpha3'=>'QAT'),
			array('iso_alpha2'=>'DO','iso_num'=>214,'iso_alpha3'=>'DOM'),
			array('iso_alpha2'=>'CZ','iso_num'=>203,'iso_alpha3'=>'CZE'),
			array('iso_alpha2'=>'RE','iso_num'=>638,'iso_alpha3'=>'REU'),
			array('iso_alpha2'=>'RO','iso_num'=>642,'iso_alpha3'=>'ROU'),
			array('iso_alpha2'=>'GB','iso_num'=>826,'iso_alpha3'=>'GBR'),
			array('iso_alpha2'=>'RU','iso_num'=>643,'iso_alpha3'=>'RUS'),
			array('iso_alpha2'=>'RW','iso_num'=>646,'iso_alpha3'=>'RWA'),
			array('iso_alpha2'=>'EH','iso_num'=>732,'iso_alpha3'=>'ESH'),
			array('iso_alpha2'=>'KN','iso_num'=>659,'iso_alpha3'=>'KNA'),
			array('iso_alpha2'=>'SH','iso_num'=>654,'iso_alpha3'=>'SHN'),
			array('iso_alpha2'=>'LC','iso_num'=>662,'iso_alpha3'=>'LCA'),
			array('iso_alpha2'=>'SM','iso_num'=>388,'iso_alpha3'=>'JAM'),
			array('iso_alpha2'=>'PM','iso_num'=>666,'iso_alpha3'=>'SPM'),
			array('iso_alpha2'=>'VC','iso_num'=>670,'iso_alpha3'=>'VCT'),
			array('iso_alpha2'=>'SV','iso_num'=>222,'iso_alpha3'=>'SLV'),
			array('iso_alpha2'=>'WS','iso_num'=>882,'iso_alpha3'=>'WSM'),
			array('iso_alpha2'=>'AS','iso_num'=>16,'iso_alpha3'=>'ASM'),
			array('iso_alpha2'=>'ST','iso_num'=>678,'iso_alpha3'=>'STP'),
			array('iso_alpha2'=>'SN','iso_num'=>686,'iso_alpha3'=>'SEN'),
			array('iso_alpha2'=>'RS','iso_num'=>688,'iso_alpha3'=>'SRB'),
			array('iso_alpha2'=>'SC','iso_num'=>690,'iso_alpha3'=>'SYC'),
			array('iso_alpha2'=>'SL','iso_num'=>694,'iso_alpha3'=>'SLE'),
			array('iso_alpha2'=>'SG','iso_num'=>702,'iso_alpha3'=>'SGP'),
			array('iso_alpha2'=>'SK','iso_num'=>703,'iso_alpha3'=>'SVK'),
			array('iso_alpha2'=>'SI','iso_num'=>705,'iso_alpha3'=>'SVN'),
			array('iso_alpha2'=>'SO','iso_num'=>706,'iso_alpha3'=>'SOM'),
			array('iso_alpha2'=>'SD','iso_num'=>729,'iso_alpha3'=>'SDN'),
			array('iso_alpha2'=>'LK','iso_num'=>144,'iso_alpha3'=>'LKA'),
			array('iso_alpha2'=>'SE','iso_num'=>752,'iso_alpha3'=>'SWE'),
			array('iso_alpha2'=>'CH','iso_num'=>756,'iso_alpha3'=>'CHE'),
			array('iso_alpha2'=>'SR','iso_num'=>740,'iso_alpha3'=>'SUR'),
			array('iso_alpha2'=>'SJ','iso_num'=>744,'iso_alpha3'=>'SJM'),
			array('iso_alpha2'=>'SZ','iso_num'=>748,'iso_alpha3'=>'SWZ'),
			array('iso_alpha2'=>'SY','iso_num'=>760,'iso_alpha3'=>'SYR'),
			array('iso_alpha2'=>'TJ','iso_num'=>762,'iso_alpha3'=>'TJK'),
			array('iso_alpha2'=>'TW','iso_num'=>158,'iso_alpha3'=>'TWN'),
			array('iso_alpha2'=>'TZ','iso_num'=>834,'iso_alpha3'=>'TZA'),
			array('iso_alpha2'=>'TD','iso_num'=>148,'iso_alpha3'=>'TCD'),
			array('iso_alpha2'=>'TF','iso_num'=>260,'iso_alpha3'=>'ATF'),
			array('iso_alpha2'=>'IO','iso_num'=>86,'iso_alpha3'=>'IOT'),
			array('iso_alpha2'=>'TH','iso_num'=>795,'iso_alpha3'=>'TKM'),
			array('iso_alpha2'=>'TL','iso_num'=>626,'iso_alpha3'=>'TLS'),
			array('iso_alpha2'=>'TG','iso_num'=>768,'iso_alpha3'=>'TGO'),
			array('iso_alpha2'=>'TK','iso_num'=>772,'iso_alpha3'=>'TKL'),
			array('iso_alpha2'=>'TO','iso_num'=>776,'iso_alpha3'=>'TON'),
			array('iso_alpha2'=>'TT','iso_num'=>780,'iso_alpha3'=>'TTO'),
			array('iso_alpha2'=>'TN','iso_num'=>788,'iso_alpha3'=>'TUN'),
			array('iso_alpha2'=>'TM','iso_num'=>795,'iso_alpha3'=>'TKM'),
			array('iso_alpha2'=>'TR','iso_num'=>792,'iso_alpha3'=>'TUR'),
			array('iso_alpha2'=>'TV','iso_num'=>798,'iso_alpha3'=>'TUV'),
			array('iso_alpha2'=>'UA','iso_num'=>804,'iso_alpha3'=>'UKR'),
			array('iso_alpha2'=>'UY','iso_num'=>858,'iso_alpha3'=>'URY'),
			array('iso_alpha2'=>'VU','iso_num'=>548,'iso_alpha3'=>'VUT'),
			array('iso_alpha2'=>'VA','iso_num'=>336,'iso_alpha3'=>'VAT'),
			array('iso_alpha2'=>'VE','iso_num'=>862,'iso_alpha3'=>'VEN'),
			array('iso_alpha2'=>'VN','iso_num'=>704,'iso_alpha3'=>'VNM'),
			array('iso_alpha2'=>'WF','iso_num'=>876,'iso_alpha3'=>'WLF'),
			array('iso_alpha2'=>'YE','iso_num'=>887,'iso_alpha3'=>'YEM'),
			array('iso_alpha2'=>'ZM','iso_num'=>894,'iso_alpha3'=>'ZMB'),
			array('iso_alpha2'=>'ZW','iso_num'=>716,'iso_alpha3'=>'ZWE'),
			array('iso_alpha2'=>'BL','iso_num'=>652,'iso_alpha3'=>'BLM'),
			array('iso_alpha2'=>'BQ','iso_num'=>535,'iso_alpha3'=>'BES'),
			array('iso_alpha2'=>'CW','iso_num'=>531,'iso_alpha3'=>'CUW'),
			array('iso_alpha2'=>'HM','iso_num'=>334,'iso_alpha3'=>'HMD'),
			array('iso_alpha2'=>'SS','iso_num'=>728,'iso_alpha3'=>'SSD'),
			array('iso_alpha2'=>'SX','iso_num'=>534,'iso_alpha3'=>'SXM'),
		);
	}
	$trans = array_column($pays, $format_code_sortie, $format_input);
	if (isset($trans[$input_pays])) {
		return $trans[$input_pays];
	}

	return $input_pays;
}
