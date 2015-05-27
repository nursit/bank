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

function presta_internetplus_inc_traiter_reponse_wha_dist($config,$m,$args,$partnerId,$keyId){
	$mode = 'wha'; // historique...
	$config_id = bank_config_id($config);

	$v=$args['v'];
	$mp = $v['mp'];
	
	if (!isset($mp['id_transaction'])
	 OR !$id_transaction=$mp['id_transaction']) {
		bank_transaction_invalide(0,
			array(
				'where' => 'wha_traiter_reponse',
				'mode' => $mode,
				'erreur' => "pas de id_transaction en retour",
				'log' => $m
			)
		);
		return array(0,false,$mp);
	}

	// verifier que la transaction est connue
	$res = sql_select("*","spip_transactions","id_transaction=".intval($id_transaction));
	if (!$row = sql_fetch($res)){
		bank_transaction_invalide($id_transaction,
			array(
				'where' => 'wha_traiter_reponse',
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => $m
			)
		);
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
		$config = array(
			'MERCHANT_ID' => $partnerId,
			'KEY_ID' => $keyId,
			'node' => $node_response,
		);
		$url = wha_url_confirm($authorisation_id,$row['montant'],$config);
		include_spip('inc/distant');
		$ack = @recuperer_page($url);
		if (!$ack
		  OR (!$unsign=wha_unsign($ack))
		  OR (!$args=wha_extract_args(reset($unsign)))
		  OR (!isset($args['c']))
		  OR (!$args['c']=='ack')) {

			bank_transaction_invalide($id_transaction,
				array(
					'where' => 'wha_traiter_reponse',
					'mode' => $mode,
					'erreur' => "pas de confirmation de debit / $url : $ack",
					'update' => true,
					'log' => $m
				)
			);
			return array($id_transaction,false,$mp);
		}

		sql_updateq("spip_transactions",array(
			"autorisation_id"=>$authorisation_id,
			"mode"=>"wha/$config_id",
			"montant_regle"=>$montant_regle,
			"date_paiement"=>date('Y-m-d H:i:s'),
			"statut"=>'ok',
			"reglee"=>'oui',
			),
			"id_transaction=".intval($id_transaction)
		);
		spip_log("wha_traiter_reponse : id_transaction $id_transaction, reglee",$mode._LOG_INFO_IMPORTANTE);
		spip_log("$m",$mode.'_autorisations'._LOG_INFO_IMPORTANTE);
	}

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));

	return array($id_transaction,true,$mp);
}

?>