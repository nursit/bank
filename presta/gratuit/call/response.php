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
 * 
 * @return array
 */
function presta_gratuit_call_response_dist(){

	// recuperer la reponse en post et la decoder
	$id_transaction = _request('id_transaction');
	$transaction_hash = _request('hash');

	if (!$row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction))){
		spip_log("id_transaction $id_transaction non trouve",'gratuit.'._LOG_ERREUR);
		return array($id_transaction,false);
	}
	if ($transaction_hash!=$row['transaction_hash']){
		spip_log("id_transaction $id_transaction, hash $transaction_hash non conforme",'gratuit.'._LOG_ERREUR);
		return array($id_transaction,false);
	}

	// verifier que la commande a bien un total nul, sinon ce mode de paiement n'est pas autorise
	if (intval($row['montant'])>0
	  OR floatval($row['montant'])>0.00){
		spip_log("id_transaction $id_transaction, montant ".$row['montant'].">0 interdit pour ce mode de paiement",'gratuit.'._LOG_CRITIQUE);
		return array($id_transaction,false);
	}

	// Ouf, le reglement a ete accepte
	sql_update("spip_transactions",
		array(
		"mode"=>sql_quote('gratuit'),
		"montant_regle"=>'montant',
		"date_paiement"=>sql_quote(date('Y-m-d H:i:s')),
		"statut"=>sql_quote('ok'),
		"reglee"=>sql_quote('oui')
		),
		"id_transaction=".intval($id_transaction)
	);
	spip_log("gratuit_response : id_transaction $id_transaction, reglee",'gratuit');

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,"",$row);
	return array($id_transaction,true);
}
?>