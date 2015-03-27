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
 * @param bool $abo
 */
function bank_lister_prestas($abo=false){
	static $prestas = array();
	if (isset($prestas[$abo]))
		return $prestas[$abo];

	$prestas[$abo] = array();
	$regexp = ($abo?"abonnement\.php$":"acte\.php$");
	foreach (creer_chemin() as $d){
		$f = $d . "presta/";
		if (@is_dir($f)){
			$all = preg_files($f, $regexp);
			foreach($all as $a){
				$a = explode("/presta/",$a);
				$a = end($a);
				$a = explode("/",$a);
				if (count($a)==3 AND $a[1]="payer"){
					$prestas[$abo][reset($a)] = true;
				}
			}
		}
	}
	ksort($prestas[$abo]);
	// a la fin
	foreach(array("cheque","virement","simu") as $m){
		if (isset($prestas[$abo][$m])){
			unset($prestas[$abo][$m]);
			$prestas[$abo][$m] = true;
		}
	}
	if (isset($prestas[$abo]['gratuit'])){
		unset($prestas[$abo]['gratuit']);
	}

	$prestas[$abo] = array_keys($prestas[$abo]);
	return $prestas[$abo];
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
	$args = (strlen($args)?"&":"").$args;
	$args = "bankp=".$config['presta'].$args;
	return generer_url_action('bank_'.$action,$args,true,true);
}


/**
 * @param string $mode
 * @param bool $abo
 * @return array
 */
function bank_config($mode,$abo=false){

	include_spip('inc/config');
	$config = array();
	if ($abo) {
		if (lire_config("bank_paiement/presta_abo/".$mode,'')){
			$config = lire_config("bank_paiement/config_abo_".$mode,'');
		}
	}
	else {
		if (lire_config("bank_paiement/presta/".$mode,'')){
			$config = lire_config("bank_paiement/config_".$mode,'');
		}
	}

	if (!$config AND $mode!=="gratuit"){
		spip_log("Configuration $mode introuvable","bank"._LOG_ERREUR);
		$config = array('erreur'=>'inconnu');
	}

	$config['presta'] = $mode; // servira pour l'aiguillage dans le futur
	$config['config'] = ($abo?'abo_':'').$mode;
	$config['type'] = ($abo?'abo':'acte');

	return $config;
}

/**
 * Transformer un tableau d'argument en liste arg=value pour le shell
 * (en echappant de maniere securisee)
 * @param $params
 * @return string
 */
function bank_shell_args($params){
	$res = "";
	foreach($params as $k=>$v){
		$res .= " ".escapeshellcmd($k)."=".escapeshellcmd($v);
	}
	return $res;
}


/**
 * @param array $transaction
 * @return string
 */
function bank_email_porteur($transaction){
	$mail = '';

	// recuperer l'email
	if (!$transaction['id_auteur']
		OR !$mail = sql_getfetsel('email','spip_auteurs','id_auteur='.intval($transaction['id_auteur']))){

		if (strpos($transaction['auteur'],"@")!==false
			AND include_spip('inc/filtres')
		  AND email_valide($transaction['auteur'])){
			$mail = $transaction['auteur'];
		}
	}

	// fallback : utiliser l'email du webmetre du site pour permettre le paiement coute que coute
	if (!$mail)
		$mail = $GLOBALS['meta']['email_webmaster'];

	return $mail;
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
		"mode" => $args['mode'],
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


function bank_response_simple($mode){
	$vars = array('id_transaction','transaction_hash','autorisation_id');
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

function bank_sign_response_simple($mode,$response = array()){
	ksort($response);
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
 * @param string $mode
 * @param null|array $response
 * @return array
 */
function bank_simple_call_response($mode="simple", $response=null){

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
				'code_erreur' => 'fail',
				'erreur' => $response['fail'],
			)
		);
	}


	// OK, on peut accepter le reglement
	$set = array(
		"mode"=>$mode,
		"autorisation_id"=>$autorisation,
		"montant_regle"=>$row['montant'],
		"date_paiement"=>date('Y-m-d H:i:s'),
		"statut"=>'ok',
		"reglee"=>'oui'
	);

	// est-ce un abonnement ?
	if (isset($response['abo_uid']) AND $response['abo_uid']){
		$set['abo_uid'] = $response['abo_uid'];
	}

	sql_updateq("spip_transactions", $set,	"id_transaction=".intval($id_transaction));
	spip_log("call_resonse : id_transaction $id_transaction, reglee",$mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));

	if (isset($response['abo_uid'])
	  AND $response['abo_uid']
	  AND $activer_abonnement = charger_fonction('activer_abonnement','abos',true)){
		// numero d'abonne = numero de transaction
		$activer_abonnement($id_transaction,$response['abo_uid'],$mode);
	}


	return array($id_transaction,true);
}