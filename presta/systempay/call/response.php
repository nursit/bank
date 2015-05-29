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
	if (isset($response['vads_url_check_src']) AND $response['vads_url_check_src']==='REC'){
		// creer la transaction maintenant si besoin !
		if ($preparer_echeance = charger_fonction('preparer_echeance','abos',true)){
			// on reinjecte le bon id de transaction ici si fourni
			if ($id_transaction = $preparer_echeance("uid:".$response['vads_subscription'])){
				$response['vads_order_id'] = $id_transaction;
			}
		}
		$recurence = true;
	}

	// depouillement de la transaction
	list($id_transaction,$success) =  systempay_traite_reponse_transaction($config, $response);

	if (strpos($response['vads_url_check_src'],"SUBSCRIBE")!==false
	  AND $abo_uid = $response['vads_subscription']
	  AND $id_transaction){

		// c'est le premier paiement de l'abonnement ?
		if (!$recurence AND $success){
			// date de fin de mois de validite de la carte
			$date_fin = "0000-00-00 00:00:00";
			if (isset($response['vads_expiry_year']) AND isset($response['vads_expiry_month'])){
				$date_fin = bank_date_fin_mois($response['vads_expiry_year'],$response['vads_expiry_month']);
			}

			if ($activer_abonnement = charger_fonction('activer_abonnement','abos',true)){
				$activer_abonnement($id_transaction,$abo_uid,$mode,$date_fin);
			}
		}


		// c'est un renouvellement en echec, il faut le resilier
		if ($recurence AND !$success){

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
