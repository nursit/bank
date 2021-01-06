<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2018 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function action_attribuer_transaction_dist($id_transaction=null, $id_auteur=null){

	if (is_null($id_transaction) or is_null($id_auteur)){
		$securiser_action = charger_fonction('securiser_action','inc');
		$arg = $securiser_action();

		list($id_transaction, $id_auteur) = explode('-', $arg);
	}

	if (autoriser('webmestre')) {

		if ($id_transaction = intval($id_transaction)
			AND $transaction = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction))
			AND $id_auteur = intval($id_auteur)
			AND $auteur = sql_fetsel("*","spip_auteurs","id_auteur=".intval($id_auteur))){

			if ($transaction['id_auteur'] == 0){
				sql_updateq('spip_transactions',array('id_auteur'=>$id_auteur),"id_transaction=" . intval($id_transaction));
			}

		}

	}
}