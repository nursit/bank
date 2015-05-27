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

function presta_internetplus_inc_traiter_reponse_wha_abo_dist($config,$m,$args,$partnerId,$keyId){
	$mode = 'wha_abo'; // historique...
	$config_id = bank_config_id($config);

	$v=$args['v'];
	$mp = $v['mp'];
	
	if (!isset($mp['id_transaction'])
	 OR !$id_transaction=$mp['id_transaction']) {
		bank_transaction_invalide(0,
			array(
				'where' => 'wha_traiter_reponse_abo',
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
				'where' => 'wha_traiter_reponse_abo',
				'mode' => $mode,
				'erreur' => "transaction inconnue",
				'log' => $m
			)
		);
		return array($id_transaction,false,$mp);
	}

	if ($row['statut']!=='ok') {

		// id abonne
		$uoid = $v['uoid'];

	  // de quoi generer la confirmation async si auteur pas connu
	  $confirm = array('node'=>$v['ru'],'partner'=>$partnerId,'key'=>$keyId);

		$confirm_offer = charger_fonction("confirm_offer","presta/internetplus/inc");
		if (!$confirm_offer($id_transaction,$uoid, $confirm)){
			bank_transaction_invalide($id_transaction,
				array(
					'where' => 'wha_traiter_reponse_abo',
					'mode' => $mode,
					'erreur' => "pas de confirmation du debit par Internet+",
					'update' => true,
					'log' => $m
				)
			);
			// invalider abo ?
			return array($id_transaction,false,$mp);
		}

		// OK ici la transaction est bien reglee
		$authorisation_id = $uoid; // url de confirmation
		sql_updateq("spip_transactions",array(
				"autorisation_id"=>$authorisation_id,
				"mode"=>"$mode/$config_id",
				"date_paiement"=>date('Y-m-d H:i:s'),
				"statut"=>'ok',
				"reglee"=>'oui',
				'abo_uid'=>$uoid,
			),
			"id_transaction=".intval($id_transaction)
		);

		// activer l'abonnement
		if ($activer_abonnement = charger_fonction('activer_abonnement','abos',true)){
			$activer_abonnement($id_transaction,$uoid,"wha/$partnerId");
		}

	}

	return array($id_transaction,true,$mp);
}
