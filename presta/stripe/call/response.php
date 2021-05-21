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

include_spip('presta/stripe/inc/stripe');

/**
 * Verifier le statut d'une transaction lors du retour de l'internaute
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_stripe_call_response_dist($config, $response = null){

	include_spip('inc/bank');
	$mode = $config['presta'];
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}


	include_spip('presta/stripe/call/autoresponse');

	// recuperer la reponse en post et la decoder, en verifiant la signature
	if (!$response){
		$response = bank_response_simple($mode);
	}

	// Stripe session_id
	$checkout_session_id = '';
	if (isset($response['checkout_session_id'])){
		$checkout_session_id = $response['checkout_session_id'];
	} elseif (isset($_REQUEST['session_id'])) {
		$checkout_session_id = $_REQUEST['session_id'];
		// CHECKOUT_SESSION_ID non remplace ?
		if ($checkout_session_id==='{CHECKOUT_SESSION_ID}' or $checkout_session_id==='CHECKOUT_SESSION_ID'){
			$checkout_session_id = null;
		}
	}

	if (!$response or (!$checkout_session_id and !$response['charge_id'] and !$response['payment_id'])){
		spip_log("call_response : checkout_session_id invalide / no payment_id", $mode . _LOG_ERREUR);
		return array(0, false);
	}

	// charger l'API Stripe avec la cle
	stripe_init_api($config);

	// Si c'est un paiement a l'acte : on doit avoir checkout_session_id + payment_id
	// Si c'est un abonnement :
	// A la creation
	//    - au premier appel, sur checkout_session_completed on a id_transaction + abo_uid
	//    - au second appel, sur invoice_finalized on a abo_uid + payment_id + billing_reason = subscription_create
	// Au renouvellemnt
	//    - sur invoice_finalized on a  abo_uid + payment_id + billing_reason = subscription_cycle

	if (empty($response['payment_id'])
		and empty($response['abo_uid'])
		and $checkout_session_id){
		$response['checkout_session_id'] = $checkout_session_id;
		try {
			$session = \Stripe\Checkout\Session::retrieve($checkout_session_id);
			if (isset($session->payment_intent) && $session->payment_intent){
				$response['payment_id'] = $session->payment_intent;
				//$payment = \Stripe\PaymentIntent::retrieve($response['payment_id']);
			}
		} catch (Exception $e) {
			if ($body = $e->getJsonBody()){
				$err = $body['error'];
				list($erreur_code, $erreur) = stripe_error_code($err);
			} else {
				$erreur = $e->getMessage();
				$erreur_code = 'error';
			}
			spip_log("call_response : checkout_session_id $checkout_session_id invalide :: #$erreur_code $erreur", $mode . _LOG_ERREUR);
		}
	}

	if (empty($response['payment_id'])){
		// enregistrer l'abo_uid sur la transaction sur le checkout_session
		if (!empty($response['abo_uid'])
			and !empty($response['id_transaction'])
			and !empty($response['transaction_hash'])) {
			sql_updateq("spip_transactions", ['abo_uid' => $response['abo_uid']], "abo_uid='' AND id_transaction=".intval($response['id_transaction'])." AND transaction_hash=".sql_quote($response['transaction_hash'])." AND reglee='non'");

			spip_log("call_response : abo_uid ".$response['abo_uid']." enregistre sur transaction #".$response['id_transaction'], $mode . _LOG_INFO_IMPORTANTE);
			// on renvoi l'id avec un false, car pour le moment non payee
			return array($response['id_transaction'], false);
		}

		spip_log("call_response : checkout_session_id invalide / no payment_id", $mode . _LOG_ERREUR);
		return array(0, false);
	}


	$recurence = false;
	if (!empty($response['billing_reason']) and in_array($response['billing_reason'], ['subscription_create', 'subscription_cycle'])) {
		$response['abo'] = true;
	}

	// c'est une creation d'abonnement et il nous manque le numero de transaction ? le retrouver
	if (
		(!empty($response['billing_reason']) and $response['billing_reason'] === 'subscription_create')
		and !empty($response['payment_id'])
		and empty($response['id_transaction'])
		and !empty($response['abo_uid'])){
		if ($t = sql_fetsel("*", "spip_transactions", "reglee='non' AND abo_uid=".sql_quote($response['abo_uid'])." AND date_transaction>".sql_quote(date('Y-m-d H:i:s', strtotime("-12hours"))))){
			$response['id_transaction'] = $t['id_transaction'];
			$response['transaction_hash'] = $t['transaction_hash'];
		}
	}

	// c'est une reconduction d'abonnement ?
	if (
		(!empty($response['billing_reason']) and $response['billing_reason'] === 'subscription_cycle')
		and !empty($response['payment_id'])
		and !empty($response['abo_uid'])){

		// verifier qu'on a pas deja traite cette recurrence !
		$where_deja = [];
		if ($response['payment_id']){
			$where_deja[] = "autorisation_id LIKE " . sql_quote("%/" . $response['payment_id']);
		}
		if ($response['charge_id']){
			$where_deja[] = "autorisation_id LIKE " . sql_quote("%/" . $response['charge_id']);
		}
		$where_deja = '(' . implode(' OR ', $where_deja) . ')';

		if ($t = sql_fetsel("*", "spip_transactions", $where_deja)){
			$response['id_transaction'] = $t['id_transaction'];
			$response['transaction_hash'] = $t['transaction_hash'];
		} // creer la transaction maintenant si besoin !
		elseif ($preparer_echeance = charger_fonction('preparer_echeance', 'abos', true)) {
			$abo_uid = $response['abo_uid'];
			$id_transaction = $preparer_echeance("uid:" . $abo_uid);
			// on reinjecte le bon id de transaction ici si fourni
			if ($id_transaction){
				$response['id_transaction'] = $id_transaction;
				$response['transaction_hash'] = sql_getfetsel('transaction_hash', 'spip_transactions', 'id_transaction=' . intval($id_transaction));
			}
			// si c'est une recurrence mais qu'on a pas su generer une transaction nouvelle il faut loger
			// avertir et sortir d'ici car on va foirer la transaction de reference sinon
			// le webmestre rejouera la transaction
			else {
				return bank_transaction_invalide(
					$response['abo_uid'] . '/' . $response['payment_id'],
					array(
						'mode' => $mode,
						'sujet' => 'Echec creation transaction echeance',
						'erreur' => "uid:" . $response['abo_uid'] . ' inconnu de $preparer_echeance',
						'log' => bank_shell_args($response),
						'update' => false,
						'send_mail' => true,
					)
				);
			}
		}
		$recurence = true;

	}

	// depouillement de la transaction
	// stripe_traite_reponse_transaction modifie $response
	list($id_transaction, $success) = stripe_traite_reponse_transaction($config, $response);

	if (($recurence or $response['abo'])
		and $abo_uid = $response['abo_uid']
		and $id_transaction){

		// c'est un premier paiement d'abonnement, l'activer
		if (!$recurence){

			if ($success){
				// date de fin de mois de validite de la carte
				$date_fin = "0000-00-00 00:00:00";
				if (isset($response['validite'])){
					list($year, $month) = explode('-', $response['validite']);
					$date_fin = bank_date_fin_mois($year, $month);
				}

				#spip_log('response:'.var_export($response,true),$mode.'db');
				#spip_log('date_fin:'.$date_fin,$mode.'db');

				if ($activer_abonnement = charger_fonction('activer_abonnement', 'abos', true)){
					$activer_abonnement($id_transaction, $abo_uid, $mode, $date_fin);
				}
			}

		} //  c'est un renouvellement
		else {
			// reussi, il faut repercuter sur l'abonnement
			if ($success){

				if ($renouveler_abonnement = charger_fonction('renouveler_abonnement', 'abos', true)){
					$renouveler_abonnement($id_transaction, $abo_uid, $mode);
				}
			}

			// echoue, il faut resilier l'abonnement
			if (!$success){
				if ($resilier = charger_fonction('resilier', 'abos', true)){
					$options = array(
						'notify_bank' => false, // pas la peine : stripe a deja resilie l'abo vu paiement refuse
						'immediat' => true,
						'message' => "[bank] Transaction #$id_transaction refusee",
						'erreur' => true,
					);
					$resilier("uid:$abo_uid", $options);
				}
			}
		}

	}

	return array($id_transaction, $success);
}
