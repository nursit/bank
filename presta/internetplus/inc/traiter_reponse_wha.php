<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2014 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;
include_spip('presta/internetplus/inc/wha_services');

function presta_internetplus_inc_traiter_reponse_wha_dist($m,$args,$partnerId,$keyId){
	$v=$args['v'];
	$mp = $v['mp'];
	
	if (!isset($mp['id_transaction'])
	 OR !$id_transaction=$mp['id_transaction']) {
		spip_log($t = "wha_traiter_reponse : pas d'id_transaction, traitement impossible : $m",'internetplus'._LOG_ERREUR);
		return array($id_transaction,false,$mp);
	}

	// verifier que la transaction est connue
	$res = sql_select("*","spip_transactions","id_transaction=".intval($id_transaction));
	if (!$row = sql_fetch($res)){
		spip_log($t = "wha_traiter_reponse : id_transaction $id_transaction inconnu: $m",'internetplus'._LOG_ERREUR);
		// on log ca dans un journal dedie
		spip_log($t,'internetplus_douteux'._LOG_INFO_IMPORTANTE);
		// on mail le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],'[WHA]Transaction Frauduleuse',$t,"sips@".$_SERVER['HTTP_HOST']);
		$message = "Une erreur est survenue, les donn&eacute;es re&ccedil;ues de la banque ne sont pas conformes. ";
		$message .= "Votre r&egrave;glement n'a pas &eacute;t&eacute; pris en compte (Ref : $id_transaction)";
		sql_updateq("spip_transactions",array("message"=>$message),"id_transaction=".intval($id_transaction));
		return array($id_transaction,false,$mp);
	}
	
	if (!$row['id_auteur']) {
		if (!$GLOBALS['visiteur_session']['id_auteur']){
			$_SESSION['wha_traiter_reponse_wha'] = array('m'=>$m,'args'=>$args,'partnerId'=>$partnerId,'keyId'=>$keyId);
			return array($id_transaction,'delayed',$mp);
		}
		else {
			sql_updateq("spip_transactions",array("id_auteur"=>($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),"id_transaction=".intval($id_transaction));
		}
	}

	if ($row['statut']!=='ok') {
		// ok, on traite le reglement
		$montant_regle = $row['montant'];

		// on verifie que le montant est bon !
		/*$montant_regle = isset($v['amt'])?$v['amt']:0;
		if ($montant_regle!=$row['montant']){
			spip_log($t = "wha_traiter_reponse : id_transaction $id_transaction, montant regle $montant_regle!=".$row['montant'].":".$m,'internetplus'._LOG_ERREUR);
			// on log ca dans un journal dedie
			spip_log($t,'internetplus_partiels'._LOG_ERREUR);
			// on est sympa avec le client, dans le doute on livre le produit
		}*/

		$authorisation_id = $v['tId'];
	
		// declencher l'url de confirmation de reglement
		$node_response = $v['rt'];
		$url = wha_url_confirm($authorisation_id,$row['montant'],$partnerId,$keyId,$node_response);
		include_spip('inc/distant');
		$ack = @recuperer_page($url);
		if (!$ack
		  OR (!$unsign=wha_unsign($ack))
		  OR (!$args=wha_extract_args(reset($unsign)))
		  OR (!isset($args['c']))
		  OR (!$args['c']=='ack')) {
			spip_log($t = "wha_traiter_reponse : id_transaction $id_transaction, pas de confirmation de debit $m / $url : $ack",'internetplus'._LOG_ERREUR);
			spip_log($t,'internetplus_confirm_hs'._LOG_ERREUR);
			$message = "Aucun r&egrave;glement n'a &eacute;t&eacute; r&eacute;alis&eacute; (pas de confirmation de debit de la part d'Internet+)";
			sql_updateq("spip_transactions",array("statut"=>'echec',"message"=>$message),"id_transaction=".intval($id_transaction));
			return array($id_transaction,false,$mp);
		}

		sql_updateq("spip_transactions",array(
			"autorisation_id"=>$authorisation_id,
			"mode"=>'wha',
			"montant_regle"=>$montant_regle,
			"date_paiement"=>date('Y-m-d H:i:s'),
			"statut"=>'ok',
			"reglee"=>'oui',
			),
			"id_transaction=".intval($id_transaction)
		);
		spip_log("wha_traiter_reponse : id_transaction $id_transaction, reglee",'internetplus'._LOG_INFO_IMPORTANTE);
		spip_log("$m",'internetplus_autorisations'._LOG_INFO_IMPORTANTE);
	}

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));

	return array($id_transaction,true,$mp);
}

?>