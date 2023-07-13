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

include_spip('inc/date');

/**
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_simu_call_response_dist($config, $response = null){

	include_spip('inc/bank');
	$mode = $config['presta'];

	// recuperer la reponse en post et la decoder, en verifiant la signature
	if (!$response){
		$response = bank_response_simple($mode);
	}

	// est-ce une simulation d'echec ?
	if (_request('status')=='fail'){
		$response['fail'] = "Simulation echec paiement";
	}
	else {
		if (empty($response['autorisation_id'])) {
			// generer un numero d'autorisation fake
			$response['autorisation_id'] = 'simu_' . substr(md5(uniqid()), 0, 16);
		}
	}

	// a la creation $response['abo'] contient le abo_uid généré avant paiement, pour activation de l'abonnement post-paiement
	// au renouvellement $response['abo'] vaut 'recurrence' et le abo_uid est dans $response['abo_uid']
	$recurrence = false;
	if (!empty($response['abo']) and empty($response['fail'])) {
		include_spip('inc/bank_recurrences');
		if ($response['abo'] === 'creation') {
			$id_transaction = $response['id_transaction'];
			$transaction_hash = $response['transaction_hash'];
			// si on ne sait pas décrire les échéances, on fail
			// et dans ce cas indiquer qu'on est dans une création d'abonnement pour le traitement au retour d'autorisation
			if (!$abo_uid = bank_recurrence_creer($id_transaction, $mode)) {
				$response['fail'] = "Simulation echec création récurrence";
			}
			else {
				$response['abo_uid'] = $abo_uid;
				// simuler un pay_id et auteur_id de la plateforme de paiement
				// qu'on devra retrouver à la recurrence
				$response['pay_id'] = 'pay_' . substr(md5(uniqid()), 0, 16);
				$response['auteur_id'] = 'cust_' . substr(md5(uniqid()), 0, 16);
			}
		}
		elseif ($response['abo'] === 'recurrence'
		  and !empty($response['abo_uid'])) {
			$id_transaction = $response['id_transaction'];
			$transaction_hash = $response['transaction_hash'];

			// verifier qu'on a bien les infos pour générer un nouveau paiement
			if (empty($response['payment_data'])
			  or !$payment_data = json_decode($response['payment_data'], true)
			  or empty($payment_data['pay_id'])
			  or empty($payment_data['auteur_id'])) {
				return bank_transaction_echec($id_transaction, array(
					'mode' => $mode,
					'erreur' => "Paramètre payment_data incorrect ou invalide pour générer un paiement récurrent (simu)",
					'log' => json_encode($response),
					'send_mail' => true,
				));
			}

			// si on a levé le define, il faut simuler un echec des paiements d'echeance d'abonnement
			// (tous si true ou celui la seulement si correspond a abo_uid)
			if (defined('_BANK_SIMU_FAIL_ECHEANCES_ABONNEMENTS')
				and in_array(_BANK_SIMU_FAIL_ECHEANCES_ABONNEMENTS, [true, $response['abo_uid']], true)) {
				$response['fail'] = "Simulation echec paiement echeance abonnement";
			}
			else {
				// rien à faire, c'est une simulation...
				// mais on simule la reutilisation du pay_id (identification qui permet de déclencher le paiement)
				// et auteur_id (identifiant client chez le prestataire de paiement)
				$response['pay_id'] = $payment_data['pay_id'];
				$response['auteur_id'] = $payment_data['auteur_id'];
			}


			$recurrence = true;
	    }
		elseif (!in_array($response['abo'], ['creation', 'recurrence']) or empty($response['abo_uid'])) {
			spip_log("call_response : valeur abo/abo_uid incoherent " . var_export($response, true), $mode . _LOG_ERREUR);
			// echec ? ne pas declencher le paiement...
			return bank_transaction_invalide($response['id_transaction'], array(
				'mode' => $mode,
				'erreur' => "Paramètre abo/abo_uid incorrect",
				'log' => json_encode($response),
				'update' => true,
			));
		}
	}

	list($id_transaction, $success) = bank_simple_call_response($config, $response);

	if (
		isset($response['abo'])
		&& $response['abo'] === 'creation'
		&& !empty($response['abo_uid'])
		&& $id_transaction
		&& $success === true
	) {
		// on passe le pay_id pour stockage dans la recurrence, il sera fourni au renouvellement pour déclencher un nouveau paiement
		$payment_data = json_encode(array('pay_id' => $response['pay_id'], 'auteur_id' => $response['auteur_id']));
		$res = bank_recurrence_activer($id_transaction, $response['abo_uid'], $mode, $payment_data);
		if (!$res) {
			bank_recurrence_invalide($id_transaction,array(
				'mode' => $mode,
				'erreur' => "echec activation abonnement " . $response['abo_uid'],
				'log' => json_encode($response),
				'sujet' => "Echec activation recurrence T #$id_transaction / ".$response['abo_uid'],
				'where' => 'bank_recurrence_activer',
			));
		}
	}

	if ($recurrence and !empty($response['abo_uid'])) {
		if ($id_transaction and $success === true) {
			// on passe le pay_id pour stockage dans la recurrence, il sera fourni au renouvellement pour déclencher un nouveau paiement
			$payment_data = json_encode(array('pay_id' => $response['pay_id'], 'auteur_id' => $response['auteur_id']));
			$res = bank_recurrence_prolonger($id_transaction, $response['abo_uid'], $mode, $payment_data);
			if (!$res) {
				bank_recurrence_invalide($id_transaction,array(
					'mode' => $mode,
					'erreur' => "echec renouvellement abonnement " . $response['abo_uid'],
					'log' => json_encode($response),
					'sujet' => "Echec renouvellement recurrence T #$id_transaction / ".$response['abo_uid'],
					'where' => 'bank_recurrence_renouveler',
				));
			}
		}

		// c'est un echec, il faut le resilier, que ce soit la premiere ou la Nieme transaction
		if (!$success){
			$res = bank_recurrence_resilier($id_transaction, $response['abo_uid'], $mode);
			if (!$res) {
				bank_recurrence_invalide($id_transaction,array(
					'mode' => $mode,
					'erreur' => "echec resiliation recurrence " . $response['abo_uid'],
					'log' => json_encode($response),
					'sujet' => "Echec resiliation recurrence suite à T #$id_transaction / ".$response['abo_uid'],
					'where' => 'bank_recurrence_resilier',
				));
			}
		}
	}

	return array($id_transaction, $success);
}
