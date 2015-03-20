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

include_spip('inc/date');

/**
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 * 
 */
function presta_simu_call_response_dist(){

	// recuperer la reponse en post et la decoder
	
	$id_transaction = _request('id_transaction');
	$transaction_hash = _request('hash');
	include_spip('inc/autoriser');
	if (!autoriser('utilisermodepaiement','simu')) {
		spip_log('simu pas autorisee','simu');
		return array($id_transaction,false);
	}
	
	if (!$row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction))){
		spip_log("id_transaction $id_transaction non trouve",'simu');
		return array($id_transaction,false);
	}
	if ($transaction_hash!=$row['transaction_hash']){
		spip_log("id_transaction $id_transaction, hash $transaction_hash non conforme",'simu');
		return array($id_transaction,false);
	}

	// est-ce une simulation d'echec ?
	if (_request('status')=='fail'){
	 	// sinon enregistrer l'absence de paiement et l'erreur
		include_spip('inc/bank');
		return bank_echec_transaction($id_transaction,
			array(
				'mode'=>"simu",
				'code_erreur' => "simu",
				'erreur' => 'Simulation echec paiement',
			)
		);
	}

	// Ouf, le reglement a ete accepte
	$set = array(
		"mode"=>'simu',
		"montant_regle"=>$row['montant'],
		"date_paiement"=>date('Y-m-d H:i:s'),
		"statut"=>'ok',
		"reglee"=>'oui',
	);
	// generer un numero d'abonne simule
	if (_request('abo')){
		$abo_uid = substr(md5("$id_transaction-".time()),0,10);
		$set['abo_uid'] = $abo_uid;
	}

	sql_updateq("spip_transactions", $set,	"id_transaction=".intval($id_transaction));
	spip_log("simu_response : id_transaction $id_transaction, reglee",'simu');

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,"",$row);

	if (_request('abo')
		AND $abo_uid
	  AND $activer_abonnement = charger_fonction('activer_abonnement','abos',true)){
		// numero d'abonne = numero de transaction
		$activer_abonnement($id_transaction,$abo_uid,'simu');
	}

	return array($id_transaction,true);
}
