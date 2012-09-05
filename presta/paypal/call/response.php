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
 * @param int $id_transaction
 * @param string $transaction_hash
 * @return array
 */
function presta_paypal_call_response(){
	// si invoice dans les post
	if ($i = _request('invoice'))
		list($id_transaction,$transaction_hash) = explode('|',$i);
	// sinon id_transaction et hash en get
	else {
		$id_transaction = _request('id_transaction');
		$transaction_hash = _request('transaction_hash');
	}

	if (!$id_transaction  OR !$row = sql_fetsel("*","spip_transactions",
					"id_transaction=".intval($id_transaction)
					." AND transaction_hash=".sql_quote($transaction_hash))) {
		spip_log("Check:Transaction $id_transaction inconnue","paypal");
		return array(0,false);
	}

	if ($row['statut']=='ok') {
		spip_log("Check:Transaction $id_transaction deja validee","paypal");
		return array($id_transaction,true);
	}

	if (isset($_POST['invoice'])) {
		include_spip('presta/paypal/inc/paypal');
		return bank_paypal_recoit_notification();
	}

	return array($id_transaction,false);
}
?>