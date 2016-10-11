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

/**
 * Retourner la liste des prestataires connus
 */
function bank_lister_prestas(){
	static $prestas = null;
	if (is_array($prestas))
		return $prestas;

	$prestas = array();
	$regexp = "(abonnement|acte)\.php$";
	foreach (creer_chemin() as $d){
		$f = $d . "presta/";
		if (@is_dir($f)){
			$all = preg_files($f, $regexp);
			foreach($all as $a){
				$a = explode("/presta/",$a);
				$a = end($a);
				$a = explode("/",$a);
				if (count($a)==3 AND $a[1]="payer"){
					$prestas[reset($a)] = true;
				}
			}
		}
	}
	ksort($prestas);
	// a la fin
	foreach(array("cheque","virement","simu") as $m){
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
function bank_url_api_retour($config,$action,$args=""){
	static $is_api = null;
	if (is_null($is_api)){
		$is_api = false;
		if (file_exists($f=_DIR_RACINE.".htaccess")){
			lire_fichier($f,$contenu);
			if (strpos($contenu,'spip.php?action=api_$1')!==false){
				$is_api = true;
			}
		}
	}

	$presta = $config['presta']."-".bank_config_id($config);
	if ($is_api){
		return generer_url_public('',$args,false,false,"bank.api/$presta/$action/");
	}
	else {
		$args = (strlen($args)?"&":"").$args;
		$args = "bankp=".$presta.$args;
		return generer_url_action('bank_'.$action,$args,true,true);
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
function bank_config($presta,$abo=false){

	$id = "";
	$mode = $presta;
	if (preg_match(",[/-][A-F0-9]{4},Uims",$presta)){
		$mode = substr($presta,0,-5);
		$id = substr($presta,-4);
	}
	if (substr($mode,-5)==="_test"){
		$mode = substr($mode,0,-5);
	}

	// renommage d'un prestataire : assurer la continuite de fonctionnement
	if ($mode=="cyberplus") $mode = "systempay";
	$type = null;
	if ($abo) $type = 'abo';

	$config = false;
	if ($mode!=="gratuit"){
		$configs = bank_lister_configs($type);
		$ids = array($id);
		if ($id) $ids[] = "";
		foreach($ids as $i) {
			foreach($configs as $k=>$c){
				if ($c['presta']==$mode
					AND (!$i OR $i == bank_config_id($c)) ){
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
			spip_log("Configuration $mode introuvable","bank"._LOG_ERREUR);
			$config = array('erreur'=>'inconnu');
		}
	}
	// gratuit est un cas particulier
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
		$config['type'] = ($abo?'abo':'acte');
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
	if (isset($ids[$hash]))
		return $ids[$hash];

	$t = $config;
	// enlever les cles non significatives
	foreach(array(
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
	foreach($t as $k=>$v){
		if (
		  // enlever les key/secret/signature/certificat
			stripos($k,"key")!==false
			OR stripos($k,"cle")!==false
			OR stripos($k,"secret")!==false
			OR stripos($k,"signature")!==false
			OR stripos($k,"certificat")!==false
			OR stripos($k,"token")!==false
		  // enlever les logo/advert/notice/adresse
			OR stripos($k,"logo")!==false
			OR stripos($k,"advert")!==false
			OR stripos($k,"notice")!==false
			OR stripos($k,"adresse")!==false
		){
			unset($t[$k]);
		}

	}
	ksort($t);
	include_spip('inc/json');
	$t = json_encode($t);
	#var_dump($t);
	return $ids[$hash] = strtoupper(substr(md5($t),0,4));
}


function bank_lister_configs($type=null){
	if ($type AND !in_array($type,array('abo','acte'))){
		$type=null;
	}

	include_spip('inc/config');
	$config = lire_config("bank_paiement/",array());
	$configs = array();
	if (is_array($config)){
		foreach($config as $k=>$v){
			if (strncmp($k,"config_",7)==0){
				if (!$type OR ($v['type']==$type) OR $v['type']=='abo_acte'){
					$configs[substr($k,7)] = $v;
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
		foreach($params as $k=>$v){
			$res .= " ".escapeshellcmd($k)."=".escapeshellcmd($v);
		}
	}
	return $res;
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
		OR !$mail = sql_getfetsel('email','spip_auteurs','id_auteur='.intval($transaction['id_auteur']))){

		if (strpos($transaction['auteur'],"@")!==false
			AND include_spip('inc/filtres')
		  AND email_valide($transaction['auteur'])){
			$mail = $transaction['auteur'];
		}
		elseif(
			(!isset($GLOBALS['visiteur_session']['id_auteur']) OR $GLOBALS['visiteur_session']['id_auteur']==$transaction['id_auteur'])
			AND isset($GLOBALS['visiteur_session']['session_email'])
			AND $GLOBALS['visiteur_session']['session_email']){
			$mail = $GLOBALS['visiteur_session']['session_nom'];
		}
	}

	// fallback : utiliser l'email du webmetre du site pour permettre le paiement coute que coute
	if (!$mail)
		$mail = $GLOBALS['meta']['email_webmaster'];

	return $mail;
}

/**
 * Recuperer le nom du porteur de la transaction (ou celui de la session a defaut ?)
 * @param array $transaction
 * @return string
 */
function bank_porteur_nom($transaction){
	$nom = '';

	// si prenom et nom en session on les utilise
	if(
	  (!isset($GLOBALS['visiteur_session']['id_auteur']) OR $GLOBALS['visiteur_session']['id_auteur']==$transaction['id_auteur'])
	  AND isset($GLOBALS['visiteur_session']['session_prenom'])
	  AND isset($GLOBALS['visiteur_session']['session_nom'])){
		$nom = $GLOBALS['visiteur_session']['session_nom'];
	}
	// recuperer le nom
	elseif (!$transaction['id_auteur']
		OR !$nom = sql_getfetsel('nom','spip_auteurs','id_auteur='.intval($transaction['id_auteur']))){

		if ($transaction['auteur'] AND strpos($transaction['auteur'],"@")===false){
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
  if(
	  (!isset($GLOBALS['visiteur_session']['id_auteur']) OR $GLOBALS['visiteur_session']['id_auteur']==$transaction['id_auteur'])
	  AND isset($GLOBALS['visiteur_session']['session_prenom'])
	  AND $GLOBALS['visiteur_session']['session_prenom']){
	  $prenom = $GLOBALS['visiteur_session']['session_prenom'];
	}

	return $prenom;
}


/**
 * Calculer la date de fin de validite d'un moyen de paiement avec son annee/mois de validite
 * @param string $annee
 * @param string $mois
 * @return string
 */
function bank_date_fin_mois($annee,$mois){
	$date_fin = mktime(0,0,0,$mois,01,$annee);
	$date_fin = strtotime("+1 month",$date_fin);
	return date('Y-m-d H:i:s',$date_fin);
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
function bank_transaction_invalide($id_transaction="",$args=array()){

	$default = array(
		'mode'=>'defaut',
		'erreur'=>'',
		'log'=>'',
		'send_mail'=>true,
		'sujet' => 'Transaction Invalide/Frauduleuse',
		'update' => false,
		'where' => 'call_response',
	);
	$args = array_merge($default,$args);

	spip_log($t=$args['where']." : ".$args['sujet']." #$id_transaction (".$args['erreur'].") ".$args['log'], $args['mode']._LOG_ERREUR);
	spip_log($t, $args['mode']."_invalides"._LOG_ERREUR);

	if ($args['send_mail']){
		// avertir le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[".$args['mode']."] ".$args['sujet'],$t);
	}

	if (intval($id_transaction) AND $args['update']){
		$message = _T("bank:erreur_transaction_echec",array("ref"=>"#$id_transaction"));
		$message .= "<br />"._T('bank:erreur_transaction_invalide');
		$set = array(
			"mode" => $args['mode'],
			"statut" => 'echec[invalide]',
			"date_paiement" => date('Y-m-d H:i:s'),
			"erreur" => $args['erreur'],
			"message" => $message,
		);
		// verifier que le champ erreur existe pour ne pas risquer de planter l'enregistrement si l'up de base n'a pas encore ete fait
		if($row=sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction))
		  AND !isset($row['erreur'])){
			unset($set['erreur']);
		}
		sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));

		return array(intval($id_transaction),false);
	}

	return array(0,false);
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
function bank_transaction_echec($id_transaction,$args=array()){

	$default = array(
		'mode'=>'defaut',
		'date_paiement'=>date('Y-m-d H:i:s'),
		'code_erreur'=>'',
		'erreur'=>'',
		'log'=>'',
		'send_mail'=>false,
		'reglee' => 'non',
		'where' => 'call_response',
	);
	$args = array_merge($default,$args);

	spip_log($t=$args['where']." : transaction $id_transaction refusee ou annulee pour : ".$args['code_erreur']." (".$args['erreur'].") ".$args['log'], $args['mode']._LOG_ERREUR);
	$set = array(
		"mode" => $args['mode'].(isset($args['config_id'])?'/'.$args['config_id']:''),
		"statut" => 'echec'.($args['code_erreur']?'['.$args['code_erreur'].']':''),
		"date_paiement" => $args['date_paiement'],
		"erreur" => $args['erreur'],
		"message" => _T("bank:erreur_transaction_echec",array("ref"=>"#$id_transaction")),
	);
	// verifier que le champ erreur existe pour ne pas risquer de planter l'enregistrement si l'up de base n'a pas encore ete fait
	if($row=sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction))
	  AND !isset($row['erreur'])){
		unset($set['erreur']);
	}

	sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));

	if ($args['send_mail']){
		// avertir le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[".$args['mode']."] Transaction Impossible",$t);
	}
	return array($id_transaction,false);
}

/*
 * Modes de paiement simples (cheque, virement)
 */


/**
 * Recuperer la reponse postee et verifier sa signature
 * @param string $mode
 * @return array|bool
 */
function bank_response_simple($mode){
	$vars = array('id_transaction','transaction_hash','autorisation_id','abo');
	$response = array();
	foreach($vars as $k) {
		if (!is_null($v = _request($k)))
		$response[$k] = $v;
	}

	if (!$s = _request('sign')
		OR $s !== bank_sign_response_simple($mode,$response)){

		spip_log("bank_response_simple : signature invalide","bank"._LOG_ERREUR);
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
function bank_sign_response_simple($mode,$response = array()){
	ksort($response);
	foreach($response as $k=>$v){
		if (is_numeric($v))
			$response[$k] = (string)$v;
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
function bank_simple_call_response($config, $response=null){

	$mode = $config['presta'];
	$config_id = bank_config_id($config);

	// recuperer la reponse en post et la decoder, en verifiant la signature
	if (!$response)
		$response = bank_response_simple($mode);

	if (!isset($response['id_transaction']) OR !isset($response['transaction_hash'])){
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => var_export($response,true),
			)
		);
	}

	$id_transaction = $response['id_transaction'];
	$transaction_hash = $response['transaction_hash'];

	if (!$row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction))){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "transaction non trouvee",
				'log' => var_export($response,true),
			)
		);
	}
	if ($transaction_hash!=$row['transaction_hash']){
		return bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "hash $transaction_hash non conforme",
				'log' => var_export($response,true),
			)
		);
	}

	$autorisation = (isset($response['autorisation_id'])?$response['autorisation_id']:'');
	if ($autorisation==="wait"){

		// c'est un reglement en attente, on le note
		$set = array(
			"mode"=>"$mode/$config_id",
			'autorisation_id'=>date('d/m/Y-H:i:s')."/".$GLOBALS['ip'],
			"date_paiement"=>date('Y-m-d H:i:s'),
			"statut"=>'attente',
		);

	}
	else {
		// si rien fourni l'autorisation refere l'id_auteur et le nom de celui qui accepte le cheque|virement
		if (!$autorisation)
			$autorisation = $GLOBALS['visiteur_session']['id_auteur']."/".$GLOBALS['visiteur_session']['nom'];

		include_spip("inc/autoriser");
		if (!autoriser('utilisermodepaiement',$mode)) {
			return bank_transaction_invalide($id_transaction,
				array(
					'mode' => $mode,
					'erreur' => "$mode pas autorisee",
				)
			);
		}

		if (!autoriser('encaisser'.$mode,'transaction',$id_transaction)){
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
					'mode'=>$mode,
					'config_id' => $config_id,
					'code_erreur' => 'fail',
					'erreur' => $response['fail'],
				)
			);
		}

		// OK, on peut accepter le reglement
		$set = array(
			"mode"=>"$mode/$config_id",
			"autorisation_id"=>$autorisation,
			"montant_regle"=>$row['montant'],
			"date_paiement"=>date('Y-m-d H:i:s'),
			"statut"=>'ok',
			"reglee"=>'oui'
		);

	}


	// est-ce un abonnement ?
	if (isset($response['abo_uid']) AND $response['abo_uid']){
		$set['abo_uid'] = $response['abo_uid'];
	}

	sql_updateq("spip_transactions", $set,	"id_transaction=".intval($id_transaction));

	// si ok on regle
	if ($set['statut']==='ok'){
		spip_log("call_resonse : id_transaction $id_transaction, reglee",$mode);

		$regler_transaction = charger_fonction('regler_transaction','bank');
		$regler_transaction($id_transaction,array('row_prec'=>$row));

		$res = true;
	}
	// sinon on trig les reglements en attente
	else {
		// cela permet de factoriser le code
		$row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction));
		pipeline('trig_bank_reglement_en_attente',array(
			'args' => array(
				'statut'=>'attente',
				'mode'=>$row['mode'],
				'type'=>$row['abo_uid']?'abo':'acte',
				'id_transaction'=>$id_transaction,
				'row'=>$row,
			),
			'data' => '')
		);

		$res = 'wait';
	}

	// Si c'est un abonnnement, activer ou resilier
	if ($id_transaction
	  AND $row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction))
	  AND $abo_uid = $row['abo_uid']){

		// c'est un paiement reussi ou en 'wait'
		if ($res){
			// date de fin de mois de validite de la carte
			$date_fin = "0000-00-00 00:00:00";
			if ($row['validite']){
				list($year,$month) = explode('-',$row['validite']);
				$date_fin = bank_date_fin_mois($year,$month);
			}

			if ($activer_abonnement = charger_fonction('activer_abonnement','abos',true)){
				$activer_abonnement($id_transaction,$abo_uid,$mode,$date_fin);
			}
		}

		// c'est un echec, il faut le resilier, que ce soit la premiere ou la Nieme transaction
		if (!$res){

			if ($resilier = charger_fonction('resilier','abos',true)){
				$options = array(
					'notify_bank' => false, // pas la peine : abo deja resilie vu paiement refuse
					'immediat' => true,
					'message' => "[bank] Transaction #$id_transaction refusee",
				);
				$resilier("uid:".$abo_uid,$options);
			}
		}

	}

	return array($id_transaction,$res);
}



/**
 * Jamais appele directement dans le plugin bank/
 * mais par une eventuelle methode abos/resilier d'un plugin externe
 * c'est une fallback quand le presta ne sait pas gerer le desabonnement en appel serveur
 * ou quand cela echoue
 *
 * @param string $uid
 * @param array|string $config
 * @return bool
 */
function bank_simple_call_resilier_abonnement($uid, $config){

	include_spip('inc/bank');
	if (!is_array($config)){
		$mode = sql_getfetsel("mode","spip_transactions","abo_uid=".sql_quote($uid)." AND statut=".sql_quote('ok')." AND mode LIKE ".sql_quote($config.'%'));
		$config = bank_config($mode);
	}

	// on envoie un mail au webmestre avec reference pour que le webmestre aille faire la resiliation manuellement
	$sujet = "[".$GLOBALS['meta']['nom_site']."] Demande Resiliation Abonnement ".$config['presta'];
	$message = "Abonne UID : $uid\nTransactions :\n";


	$trans = sql_allfetsel("id_transaction,date_paiement,montant","spip_transactions","abo_uid=".sql_quote($uid)." AND statut=".sql_quote('ok')." AND mode LIKE ".sql_quote($config['presta'].'%'));
	foreach($trans as $tran){
		$message .= "#".$tran['id_transaction']." ".$tran['date_paiement']." ".affiche_monnaie($tran['montant'])."\n";
	}

	$envoyer_mail = charger_fonction("envoyer_mail","inc");
	$envoyer_mail($GLOBALS['meta']['email_webmaster'],$sujet,$message);

	return false;
}