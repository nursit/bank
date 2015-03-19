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
 * Generer le message d'erreur et l'enregistrement en base d'une transaction echouee
 * avec log et email eventuel
 *
 * @param int $id_transaction
 * @param string $mode
 *   mode de paiement
 * @param string $date_paiement
 *   date du paiement
 * @param string $code_erreur
 *   code erreur
 * @param string $erreur
 *   texte en clair de l'erreur
 * @param string $log
 *   texte complementaire pour les logs
 * @param bool $send_mail
 *   avertir le webmestre par mail
 */
function bank_echec_transaction($id_transaction,$mode,$date_paiement,$code_erreur="",$erreur="",$log="",$send_mail=false){

	spip_log($t="call_response : transaction $id_transaction refusee ou annulee pour : $code_erreur ($erreur) ".$log, $mode._LOG_ERREUR);
	$set = array(
		"mode" => $mode,
		"statut" => 'echec'.($code_erreur?'['.$code_erreur.']':''),
		"date_paiement" => $date_paiement,
		// TODO : stocker l'erreur en base pour l'admin
		//"erreur" => $erreur,
		"message" => "Aucun r&egrave;glement n'a &eacute;t&eacute; r&eacute;alis&eacute;. (Transaction Ref. #$id_transaction)",
	);

	sql_updateq("spip_transactions",$set,"id_transaction=".intval($id_transaction));

	if ($send_mail){
		// avertir le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],"[$mode] Transaction Impossible",$t);
	}
}