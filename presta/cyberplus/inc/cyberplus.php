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

function cyberplus_site_id(){
	return _CYBERPLUS_SITE_ID;
}

/**
 * Generer les hidden du form en signant les parametres au prealable
 * @param array $parms
 * @return string
 */
function cyberplus_form_hidden($parms){
	$parms['signature'] = cyberplus_signe_contexte($parms,_CYBERPLUS_CLE);
	$hidden = "";
	foreach($parms as $k=>$v){
		$hidden .= "<input type='hidden' name='$k' value='".str_replace("'", "&#39;", $v)."' />";
	}

	return $hidden;
}


/**
 * Signer le contexte en SHA, avec une cle secrete $key
 * @param array $contexte
 * @param string $key
 * @return string
 */
function cyberplus_signe_contexte($contexte,$key) {

	// on ne prend que les infos vads_*
	// a signer
	$sign = array();
	foreach($contexte as $k=>$v) {
		if (strncmp($k,'vads_',5)==0)
			$sign[$k] = $v;
	}
	// tri des parametres par ordre alphabétique
	ksort($sign);
	$contenu_signature = implode("+",$sign);
	$contenu_signature .= "+$key";

	$s = sha1($contenu_signature);
	return $s;
}


/**
 * Verifier la signature de la reponse Cyberplus
 * @param $values
 * @param $key
 * @return bool
 */
function cyberplus_verifie_signature($values,$key) {
	$signature = cyberplus_signe_contexte($values,$key);

	if(isset($values['signature'])
		AND ($values['signature'] == $signature))	{

		return true;
	}

	return false;
}

/**
 * Recuperer le POST/GET de la reponse dans un tableau
 * en verifiant la signature
 *
 * @param $key
 * @return array|bool
 */
function cyberplus_recupere_reponse($key){
	$reponse = array();
	foreach($_REQUEST as $k=>$v){
		if (strncmp($k,'vads_',5)==0){
			$reponse[$k] = $v;
		}
	}
	$reponse['signature'] = (isset($_REQUEST['signature'])?$_REQUEST['signature']:'');

	if (!cyberplus_verifie_signature($reponse,$key))
		return false;

	return $reponse;
}


function cyberplus_traite_reponse_transaction($response, $mode="cyberplus"){
	#var_dump($response);

	$id_transaction = $response['vads_order_id'];
	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction))){
		spip_log($t = "call_response : id_transaction $id_transaction inconnu:".bank_shell_args($response),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . "_douteux");
		// on mail le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[$mode]Transaction Frauduleuse",$t,"$mode@".$_SERVER['HTTP_HOST']);
		$message = "Une erreur est survenue, les donn&eacute;es re&ccedil;ues de la banque ne sont pas conformes. ";
		$message .= "Votre r&egrave;glement n'a pas &eacute;t&eacute; pris en compte (Ref : $id_transaction)";
		$set = array(
			"mode" => $mode,
			"message" => $message,
			'statut' => 'echec',
		);
		sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));
		return array($id_transaction,false);
	}

	// est-ce bien un debit
	if ($response['vads_operation_type']!=="DEBIT"){
		spip_log($t = "call_response : vads_operation_type=".$response['vads_operation_type']." non pris en charge sur id_transaction $id_transaction :".bank_shell_args($response),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . "_operations");
		// on mail le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[$mode]Transaction non prise en charge",$t,"$mode@".$_SERVER['HTTP_HOST']);
		return array($id_transaction,false);
	}

	// ok, on traite le reglement
	$date = $response['vads_effective_creation_date'];
	$date_paiement = sql_format_date(
		substr($date,0,4), //annee
		substr($date,4,2), //mois
		substr($date,6,2), //jour
		substr($date,8,2), //Heures
		substr($date,10,2), //min
		substr($date,12,2) //sec
	);

	$erreur = array(
		cyberplus_response_code($response['vads_result']),
		cyberplus_auth_response_code($response['vads_auth_result'])
	);
	$erreur = array_filter($erreur);
	$erreur = trim(implode(' ',$erreur));
	$authorisation_id = $response['vads_payment_certificate'];
	$transaction = $response['vads_auth_number'];

	if (!$transaction
	  OR !$authorisation_id
	  OR !in_array($response['vads_trans_status'],array('AUTHORISED','CAPTURED'))
	  OR $erreur){
	 	// regarder si l'annulation n'arrive pas apres un reglement (internaute qui a ouvert 2 fenetres de paiement)
	 	if ($row['reglee']=='oui') return array($id_transaction,true);
	 	// sinon enregistrer l'absence de paiement et l'erreur
		spip_log($t="call_response : transaction $id_transaction refusee :[$erreur]:".bank_shell_args($response),$mode);
		$set = array(
			"mode"=>$mode,
			"statut"=>'echec['.$response['vads_result'].']',
			'date_paiement'=>$date_paiement
		);
		sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));
		if (intval($response['vads_result'])==2){
			// avertir le webmestre
			$envoyer_mail = charger_fonction('envoyer_mail','inc');
			$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[$mode]Transaction Impossible",$t,"$mode@".$_SERVER['HTTP_HOST']);
		}
		// TODO : stocker l'erreur en base pour l'admin
		$message = "Aucun r&egrave;glement n'a &eacute;t&eacute; r&eacute;alis&eacute;";/*.($erreur?" ($erreur)":"")*/
		sql_updateq("spip_transactions",array("message"=>$message),"id_transaction=".intval($id_transaction));
		return array($id_transaction,false);
	}

	// Ouf, le reglement a ete accepte

	// on verifie que le montant est bon !
	$montant_regle = $response['vads_effective_amount']/100;
	if ($montant_regle!=$row['montant']){
		spip_log($t = "call_response : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".bank_shell_args($response),$mode);
		// on log ca dans un journal dedie
		spip_log($t,$mode . '_reglements_partiels');
	}

	$set = array(
		"autorisation_id" => "$transaction/$authorisation_id",
		"mode" => $mode,
		"montant_regle" => $montant_regle,
		"date_paiement" => $date_paiement,
		"statut"=>'ok',
		"reglee"=>'oui'
	);

	// si on a les infos de validite / card number, on les note ici
	if (isset($response['vads_expiry_year'])){
		$set['refcb'] = $response['vads_expiry_year']."/".$response['vads_expiry_month'];

		if (isset($response['vads_card_brand']))
			$set['refcb'] .= " ".$response['vads_card_brand'];
		if (isset($response['vads_card_number']))
			$set['refcb'] .= " ".$response['vads_card_number'];
	}

	sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));
	spip_log("call_response : id_transaction $id_transaction, reglee",$mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,"",$row);
	return array($id_transaction,true);
}


function cyberplus_response_code($code){
	if ($code==0) return '';
	$pre = 'Erreur : ';
	$codes = array(
		2 => 'Le commerçant doit contacter la banque du porteur.',
		5 => 'Paiement refusé.',
		17 => 'Annulation du client',
		30 => 'Erreur de format de la requête. A mettre en rapport avec la valorisation du champ vads_extra_result.',
		96 => 'Erreur technique lors du paiement.',
	);

	if (isset($codes[intval($code)]))
		return $pre.$codes[intval($code)];
	return $pre ? $pre : 'Erreur inconnue';
}

function cyberplus_auth_response_code($code){
	if ($code==0) return '';
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

	if (isset($codes[intval($code)]))
		return $pre.$codes[intval($code)];
	return $pre ? $pre : 'Erreur inconnue';
}
