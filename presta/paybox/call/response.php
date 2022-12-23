<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('presta/paybox/inc/paybox');
include_spip('inc/date');

/**
 * Verifier le statut d'une transaction lors du retour de l'internaute
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_paybox_call_response_dist($config, $response = null){

	include_spip('inc/bank');
	$mode = $config['presta'];

	if (!$response)// recuperer la reponse en post et la decoder
	{
		$response = paybox_response();
	}

	if (!$response){
		return array(0, false);
	}

	if (!isset($response['ETAT_PBX'])) {
		$response['ETAT_PBX'] = '';
	}

	if ($response['ETAT_PBX']==='PBX_RECONDUCTION_ABT'){
		// c'est un revouvellement initie par paybox
		// verifier qu'on a pas deja traite cette recurrence !
		if ($t2 = sql_fetsel("*", "spip_transactions", "autorisation_id=" . sql_quote($response['trans'] . "/" . $response['auth']))){
			$response['id_transaction'] = $t2['id_transaction'];
		} // creer la transaction maintenant si besoin !
		elseif ($preparer_echeance = charger_fonction('preparer_echeance', 'abos', true)) {
			// on reinjecte le bon id de transaction ici si fourni
			if ($id_transaction = $preparer_echeance("uid:" . $response['abo'])){
				$response['id_transaction'] = $id_transaction;
			}
			// si c'est une recurrence mais qu'on a pas su generer une transaction nouvelle il faut loger
			// avertir et sortir d'ici car on va foirer la transaction de reference sinon
			// le webmestre rejouera la transaction
			else {
				return bank_transaction_invalide(
					intval($response['id_transaction']) . 'PBX_RECONDUCTION_ABT',
					array(
						'mode' => $mode,
						'sujet' => 'Echec creation transaction echeance',
						'erreur' => "uid:" . $response['abo'] . ' inconnu de $preparer_echeance',
						'log' => bank_shell_args($response),
						'update' => false,
						'send_mail' => true,
					)
				);
			}
		}
	}

	// depouillement de la transaction
	list($id_transaction, $success) = paybox_traite_reponse_transaction($config, $response);

	if ($response['abo'] AND $id_transaction){

		// c'est un premier paiement d'abonnement, l'activer
		if ($response['ETAT_PBX']!=='PBX_RECONDUCTION_ABT'
			AND $success){

			// date de fin de mois de validite de la carte
			$date_fin = bank_date_fin_mois(2000+intval(substr($response['valid'], 0, 2)), substr($response['valid'], 2, 2));

			#spip_log('response:'.var_export($response,true),$mode.'db');
			#spip_log('date_fin:'.$date_fin,$mode.'db');

			// id_transaction contient toute la trame IDB_xx deriere le numero
			// on ne retient que la valeur entiere
			$id_transaction = intval($id_transaction);

			if ($activer_abonnement = charger_fonction('activer_abonnement', 'abos', true)){
				$activer_abonnement($id_transaction, $response['abo'], $mode, $date_fin);
			}
		}

		// c'est un renouvellement reussi, il faut repercuter sur l'abonnement
		if ($response['ETAT_PBX']==='PBX_RECONDUCTION_ABT'
			AND $success){

			if ($renouveler_abonnement = charger_fonction('renouveler_abonnement', 'abos', true)){
				$renouveler_abonnement($id_transaction, $response['abo'], $mode);
			}
		}

		// c'est un renouvellement en echec, il faut le resilier
		if ($response['ETAT_PBX']==='PBX_RECONDUCTION_ABT'
			AND !$success){

			if ($resilier = charger_fonction('resilier', 'abos', true)){
				$options = array(
					'notify_bank' => false, // pas la peine : paybox a deja resilie l'abo vu paiement refuse
					'immediat' => true,
					'message' => "[bank] Transaction #$id_transaction refusee",
					'erreur' => true,
				);
				$resilier("uid:" . $response['abo'], $options);
			}

		}

	}
	return array($id_transaction, $success);
}
