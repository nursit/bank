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

function presta_internetplus_inc_traiter_reponse_wha_abo_dist($m,$args,$partnerId,$keyId){
	$v=$args['v'];
	$mp = $v['mp'];
	
	if (!isset($mp['id_transaction'])
	 OR !$id_transaction=$mp['id_transaction']) {
		spip_log($t = "wha_traiter_reponse : pas d'id_transaction, traitement impossible : $m",'internetplus_abo'._LOG_ERREUR);
		return array($id_transaction,false,$mp);
	}

	// verifier que la transaction est connue
	$res = sql_select("*","spip_transactions","id_transaction=".intval($id_transaction));
	if (!$row = sql_fetch($res)){
		spip_log($t = "wha_traiter_reponse : id_transaction $id_transaction inconnu: $m",'internetplus_abo'._LOG_ERREUR);
		// on log ca dans un journal dedie
		spip_log($t,'wha_abo_douteux');
		// on mail le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],'[WHA]Transaction Frauduleuse',$t,"sips@".$_SERVER['HTTP_HOST']);
		$message = "Une erreur est survenue, les donn&eacute;es re&ccedil;ues de la banque ne sont pas conformes. ";
		$message .= "Votre r&egrave;glement n'a pas &eacute;t&eacute; pris en compte (Ref : $id_transaction)";
		sql_updateq("spip_transactions",array("mode"=>'wha_abo',"message"=>$message),"id_transaction=".intval($id_transaction));
		return array($id_transaction,false,$mp);
	}

	if ($row['statut']!=='ok') {

		// id abonne
		$uoid = $v['uoid'];

	  // de quoi generer la confirmation async si auteur pas connu
	  $confirm = array('node'=>$v['ru'],'partner'=>$partnerId,'key'=>$keyId);

		$confirm_offer = charger_fonction("confirm_offer","presta/internetplus/inc");
		if (!$confirm_offer($id_transaction,$uoid, $confirm)){
			$message = "Aucun r&egrave;glement n'a &eacute;t&eacute; r&eacute;alis&eacute; (pas de confirmation de debit de la part d'Internet+)";
			sql_updateq("spip_transactions",array("statut"=>'echec',"message"=>$message),"id_transaction=".intval($id_transaction));
			// invalider abo ?
			return array($id_transaction,false,$mp);
		}

		// OK ici la transaction est bien reglee
		$authorisation_id = $uoid; // url de confirmation
		sql_updateq("spip_transactions",array(
				"autorisation_id"=>$authorisation_id,
				"mode"=>'wha_abo',
				"date_paiement"=>date('Y-m-d H:i:s'),
				"statut"=>'ok',
				"reglee"=>'oui'
			),
			"id_transaction=".intval($id_transaction)
		);

		// activer l'abonnement
		$activer_abonnement = charger_fonction('activer_abonnement','abos');
		$activer_abonnement($id_transaction,$uoid,"wha/$partnerId");

	}

	return array($id_transaction,true,$mp);
}
