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
		$message = "Aucun r&egrave;glement n'a &eacute;t&eacute; r&eacute;alis&eacute;. (Transaction Ref. #$id_transaction)";
		$message .= "<br />Une erreur est survenue, les donn&eacute;es re&ccedil;ues ne sont pas conformes.";
		$set = array(
			"mode" => $args['mode'],
			"statut" => 'echec[invalide]',
			"date_paiement" => date('Y-m-d H:i:s'),
			// TODO : stocker l'erreur en base pour l'admin
			//"erreur" => $erreur,
			"message" => $message,
		);
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
		// TODO : stocker l'erreur en base pour l'admin
		//"erreur" => $erreur,
		"message" => "Aucun r&egrave;glement n'a &eacute;t&eacute; r&eacute;alis&eacute;. (Transaction Ref. #$id_transaction)",
	);

	sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));

	if ($args['send_mail']){
		// avertir le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[".$args['mode']."] Transaction Impossible",$t);
	}
	return array($id_transaction,false);
}