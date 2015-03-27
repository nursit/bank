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
 * Verifier le statut d'une transaction lors du retour de l'internaute
 *
 * @param string $mode
 * @param null|array $response
 * @return array
 */
function presta_paypal_call_response($mode='paypal', $response=null){

	include_spip('inc/bank');
	$config = bank_config($mode);

	if (!$response){
		$response = array();
		foreach ($_POST as $key => $value){
			$response[$key] = $value;
		}
		// si invoice dans les post
		if (isset($response['invoice'])){
			list($response['id_transaction'],$response['transaction_hash']) = explode('|',$response['invoice']);
		}
		// sinon id_transaction et hash en get ?
		else {
			$response['id_transaction'] = _request('id_transaction');
			$response['transaction_hash'] = _request('transaction_hash');
		}
	}

	if (!isset($response['id_transaction']) OR !isset($response['transaction_hash'])){
		spip_log("id_transaction ou transaction_hash absent ".var_export($response,true),$mode._LOG_ERREUR);
		return array(0,false);
	}

	$id_transaction = $response['id_transaction'];
	$transaction_hash = $response['transaction_hash'];

	if (!$id_transaction
		OR !$row = sql_fetsel("*","spip_transactions", "id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash))) {
		spip_log("Check:Transaction $id_transaction inconnue",$mode._LOG_ERREUR);
		return array(0,false);
	}

	if ($row['statut']=='ok') {
		spip_log("Check:Transaction $id_transaction deja validee",$mode);
		return array($id_transaction,true);
	}

	include_spip('presta/paypal/inc/paypal');
	return paypal_traite_response($response,$config);
}

