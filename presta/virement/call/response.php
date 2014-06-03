<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * Olivier Tétard
 * (c) 2014 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/date');

/**
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * 
 * @return array
 */
function presta_virement_call_response_dist(){

	// recuperer la reponse en post et la decoder
	$id_transaction = _request('id_transaction');
	$transaction_hash = _request('hash');

	if(!$row = sql_fetsel('*', 'spip_transactions', 'id_transaction='.sql_quote(intval($id_transaction)))) {
		spip_log("id_transaction $id_transaction non trouve", 'gratuit.'._LOG_ERREUR);
		return array($id_transaction,false);
	}
	if($transaction_hash != $row['transaction_hash']) {
		spip_log("id_transaction $id_transaction, hash $transaction_hash non conforme", 'gratuit.'._LOG_ERREUR);
		return array($id_transaction, false);
	}

	// l'autorisation refere l'id_auteur et le nom de celui qui accepte le virement
	$autorisation = $GLOBALS['visiteur_session']['id_auteur']."/".$GLOBALS['visiteur_session']['nom'];

	include_spip("inc/autoriser");
	if(!autoriser('encaisservirement', 'transaction', $id_transaction)) {
		spip_log("id_transaction $id_transaction, tentative d'encaisser un virement par auteur #$autorisation pas autorise", 'virement.'._LOG_CRITIQUE);
		return array($id_transaction, false);
	}

	// OK, on peut accepter le reglement
	$set = array("mode" => sql_quote('virement'),
		     "autorisation_id" => sql_quote($autorisation),
		     "montant_regle" => 'montant',
		     "date_paiement" => sql_quote(date('Y-m-d H:i:s')),
		     "statut" => sql_quote('ok'),
		     "reglee" => sql_quote('oui')
		     );
	sql_update("spip_transactions", $set, "id_transaction=".sql_quote(intval($id_transaction)));
	spip_log("virement_response : id_transaction $id_transaction, reglee", 'virement');

	$regler_transaction = charger_fonction('regler_transaction', 'bank');
	$regler_transaction($id_transaction, "", $row);
	return array($id_transaction, true);
}