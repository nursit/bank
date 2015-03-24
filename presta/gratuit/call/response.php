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
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param null|array $response
 * @param string $mode
 * @return array
 */
function presta_gratuit_call_response_dist($response=null, $mode='gratuit'){

	// recuperer la reponse en post et la decoder, en verifiant la signature
	if (!$response)
		$response = bank_response_simple($mode);

	if (!isset($response['id_transaction']) OR !isset($response['transaction_hash'])){
		spip_log("id_transaction ou transaction_hash absent ".var_export($response,true),$mode._LOG_ERREUR);
		return array(0,false);
	}

	$id_transaction = $response['id_transaction'];
	$transaction_hash = $response['hash'];

	if (!$row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction))){
		spip_log("id_transaction $id_transaction non trouve",$mode._LOG_ERREUR);
		return array($id_transaction,false);
	}
	if ($transaction_hash!=$row['transaction_hash']){
		spip_log("id_transaction $id_transaction, hash $transaction_hash non conforme",$mode._LOG_ERREUR);
		return array($id_transaction,false);
	}

	// verifier que la commande a bien un total nul, sinon ce mode de paiement n'est pas autorise
	if (intval($row['montant'])>0
	  OR floatval($row['montant'])>0.00){
		spip_log("id_transaction $id_transaction, montant ".$row['montant'].">0 interdit pour ce mode de paiement",$mode._LOG_CRITIQUE);
		return array($id_transaction,false);
	}

	// OK, on peut accepter le reglement
	$set = array(
		"mode"=>$mode,
		"montant_regle"=>$row['montant'],
		"date_paiement"=>date('Y-m-d H:i:s'),
		"statut"=>'ok',
		"reglee"=>'oui'
	);
	sql_updateq("spip_transactions", $set,	"id_transaction=".intval($id_transaction));
	spip_log("call_resonse : id_transaction $id_transaction, reglee",$mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));

	return array($id_transaction,true);
}

