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

	// Ouf, le reglement a ete accepte
	
	sql_update("spip_transactions",
		array(
		"mode"=>sql_quote('simu'),
		"montant_regle"=>'montant',
		"date_paiement"=>'NOW()',
		"statut"=>sql_quote('ok'),
		"reglee"=>sql_quote('oui')
		),
		"id_transaction=".intval($id_transaction)
	);
	spip_log("simu_response : id_transaction $id_transaction, reglee",'simu');

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,"",$row);

	if (_request('abo')
	  AND $activer_abonement = charger_fonction('activer_abonnement','abos',true)){
		// numero d'abonne = numero de transaction
		$activer_abonement($id_transaction,$id_transaction,'simu');
	}

	return array($id_transaction,true);
}
