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

include_spip('presta/systempay/inc/systempay');

/**
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_systempay_call_response_dist($config, $response=null){

	include_spip('inc/bank');
	$mode = $config['presta'];

	if (!$response){
		// recuperer la reponse en post et la decoder
		$response = systempay_recupere_reponse($config);
	}

	if (!$response) {
		return array(0,false);
	}


	$recurence = false;
	// c'est une reconduction d'abonnement ?
	if (isset($response['vads_url_check_src']) AND in_array($response['vads_url_check_src'],array('REC','RETRY'))
		  AND isset($response['vads_recurrence_number']) AND $response['vads_recurrence_number']){

		// si la transaction reference n'existe pas ou a deja ete payee c'est bien une recurence
		// sinon c'est le paiement de la premiere transaction
		$trans = sql_fetsel("*","spip_transactions","id_transaction=".intval($response['vads_order_id']));
		if (!$trans OR $trans['statut']=='ok'){
			// verifier qu'on a pas deja traite cette recurrence !
			if ($trans = sql_fetsel("*","spip_transactions","autorisation_id=".sql_quote($response['vads_order_id']."/".$response['vads_trans_id']))){
				$response['vads_auth_number'] = $response['vads_order_id'];
				$response['vads_payment_certificate'] = $response['vads_trans_id'];
				$response['vads_order_id'] = $trans['id_transaction'];
			}
			// creer la transaction maintenant si besoin !
			elseif ($preparer_echeance = charger_fonction('preparer_echeance','abos',true)){
				// on reinjecte le bon id de transaction ici si fourni
				if ($id_transaction = $preparer_echeance("uid:".$response['vads_subscription'])){
					$response['vads_auth_number'] = $response['vads_order_id'];
					$response['vads_payment_certificate'] = $response['vads_trans_id'];
					$response['vads_order_id'] = $id_transaction;
				}
			}
			$recurence = true;
		}
	}

	// depouillement de la transaction
	list($id_transaction,$success) =  systempay_traite_reponse_transaction($config, $response);

	if (
		($recurence
			OR strpos($response['vads_page_action'],"SUBSCRIBE")!==false
	    OR in_array($response['vads_url_check_src'],array('RETRY')))
	  AND isset($response['vads_subscription'])
	  AND $abo_uid = $response['vads_subscription']
	  AND $id_transaction){

		$transaction = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction));
		// c'est le premier paiement de l'abonnement ?
		if (!$recurence AND $success){
			// date de fin de mois de validite de la carte (mais pas pour un SEPA qui se reconduit tout seul)
			$date_fin = "0000-00-00 00:00:00";
			if (isset($response['vads_expiry_year'])
			  AND isset($response['vads_expiry_month'])
			  AND strncmp($transaction['refcb'],'SEPA',4)!==0){
				$date_fin = bank_date_fin_mois($response['vads_expiry_year'],$response['vads_expiry_month']);
			}

			if ($activer_abonnement = charger_fonction('activer_abonnement','abos',true)){
				$activer_abonnement($id_transaction,$abo_uid,$mode,$date_fin);
			}
		}

		// c'est un renouvellement reussi, il faut repercuter sur l'abonnement
		if ($recurence
			AND $success){

			if ($renouveler_abonnement = charger_fonction('renouveler_abonnement','abos',true)){
				$renouveler_abonnement($id_transaction,$abo_uid,$mode);
			}
		}


		// c'est un echec, il faut le resilier, que ce soit la premiere ou la Nieme transaction
		if (!$success){

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

	return array($id_transaction,$success);
}
