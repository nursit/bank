<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * Encaisser le reglement differe d'une transaction
 * (on a recu le cheque, le virement)
 * Utilise dans le back-office sur les transactions en attente de paiement
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

function formulaires_rembourser_transaction_charger_dist($id_transaction){

	$transaction = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction));
	if ($transaction['statut']!=='ok'){
		return false;
	}

	include_spip('inc/autoriser');
	if (!autoriser('rembourser', 'transaction', $id_transaction)){
		return false;
	}

	$valeurs = array(
		'_id_transaction' => $id_transaction,
		'_mode' => $transaction['mode'],
		'raison' => '',
		'_autorisation_id_prefixe' => remboursement_prefixe(),
	);

	return $valeurs;
}

function formulaires_rembourser_transaction_verifier_dist($id_transaction){
	$erreurs = array();
	$raison = _request('raison');
	if (!$raison){
		$erreurs['raison'] = _T('info_obligatoire');
	}
	return $erreurs;
}

function formulaires_rembourser_transaction_traiter_dist($id_transaction){

	$raison = _request('raison');
	$raison = "<hr />\n" . date('Y-m-d H:i:s') . ' REMBOURSEMENT ' . remboursement_prefixe() . " : " . $raison;

	$res = array();
	$rembourser_transaction = charger_fonction('rembourser_transaction', 'bank');

	if ($rembourser_transaction($id_transaction, array('message' => $raison))){
		$res['message_ok'] = _L('Transaction remboursée');
	} else {
		$res['message_erreur'] = _L('Erreur Technique, remboursement impossible');
	}

	return $res;
}

function remboursement_prefixe(){
	return "#" . $GLOBALS['visiteur_session']['id_auteur'] . "-" . $GLOBALS['visiteur_session']['nom'];
}