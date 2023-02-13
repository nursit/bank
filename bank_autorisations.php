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

// pipeline de chargement
function bank_autoriser($flux){
	return $flux;
}

function autoriser_bank_configurer($faire, $mode = '', $id = 0, $qui = NULL, $opt = NULL){
	return autoriser('webmestre');
}

function autoriser_utilisermodepaiement_dist($faire, $mode = '', $id = 0, $qui = NULL, $opt = NULL){
	include_spip("presta/$mode/config");
	$fonctions = array('autoriser_' . $mode . '_' . $faire, 'autoriser_' . $mode . '_' . $faire . '_dist', 'autoriser_' . $mode, 'autoriser_' . $mode . '_dist');
	foreach ($fonctions as $f){
		if (function_exists($f)){
			return $f($faire, $mode, $id, $qui, $opt);
		}
	}
	return true;
}

function autoriser_utilisermodepaiementabo_dist($faire, $mode = '', $id = 0, $qui = NULL, $opt = NULL){
	include_spip("presta/$mode/config");
	$fonctions = array('autoriser_' . $mode . '_' . $faire, 'autoriser_' . $mode . '_' . $faire . '_dist', 'autoriser_' . $mode, 'autoriser_' . $mode . '_dist');
	foreach ($fonctions as $f){
		if (function_exists($f)){
			return $f($faire, $mode, $id, $qui, $opt);
		}
	}
	return true;
}

function autoriser_transaction_iconifier_dist($faire, $mode = '', $id = 0, $qui = NULL, $opt = NULL){
	return false;
}

function autoriser_bankrecurrence_iconifier_dist($faire, $mode = '', $id = 0, $qui = NULL, $opt = NULL){
	return false;
}

function autoriser_bankrecurrence_modifier_dist($faire, $mode = '', $id = 0, $qui = NULL, $opt = NULL){
	return false;
}

function autoriser_bankrecurrence_annuler_dist($faire, $mode = '', $id = 0, $qui = NULL, $opt = NULL){
	if (!autoriser('webmestre')) {
		return false;
	}
	if (intval($id) and sql_countsel('spip_bank_recurrences', 'id_bank_recurrence='.intval($id)." AND statut='valide'")) {
		return true;
	}
	return false;
}

/**
 * Seuls les webmestres peuvent encaisser un cheque
 * webmaster.
 * @param $faire
 * @param $type
 * @param $id_transaction
 * @param $qui
 * @param $opt
 * @return bool
 */
function autoriser_transaction_encaissercheque_dist($faire, $type, $id_transaction, $qui, $opt){
	return autoriser('webmestre');
}

/**
 * Seuls les webmestres peuvent encaisser un virement
 * webmaster.
 * @param $faire
 * @param $type
 * @param $id_transaction
 * @param $qui
 * @param $opt
 * @return bool
 */
function autoriser_transaction_encaisservirement_dist($faire, $type, $id_transaction, $qui, $opt){
	return autoriser('webmestre');
}

/**
 * Tout le monde peut encaisser sa simu de virement
 *
 * @param $faire
 * @param $type
 * @param $id_transaction
 * @param $qui
 * @param $opt
 * @return bool
 */
function autoriser_transaction_encaissersimu_dist($faire, $type, $id_transaction, $qui, $opt){
	return true;
}

/**
 * Seuls les webmestres peuvent rembourser une transaction
 * webmaster.
 * @param $faire
 * @param $type
 * @param $id_transaction
 * @param $qui
 * @param $opt
 * @return bool
 */
function autoriser_transaction_rembourser_dist($faire, $type, $id_transaction, $qui, $opt){
	return autoriser('webmestre');
}

/**
 * @param $faire
 * @param $mode
 * @param $id
 * @param $qui
 * @param $opt
 * @return false
 */
function autoriser_transaction_abandonner_dist($faire, $mode = '', $id = 0, $qui = NULL, $opt = NULL){

	if (!$id) {
		return false;
	}

	$transaction = sql_fetsel('id_auteur, statut', 'spip_transactions', 'id_transaction='.intval($id));
	if (empty($opt['statut'])) {
		$statut = $transaction['statut'];
	}
	else {
		$statut = $opt['statut'];
	}

	if ($statut === 'commande' and in_array($transaction['id_auteur'], array(0, $qui['id_auteur']))) {
		return true;
	}

	if ($statut === 'attente') {
		return autoriser('webmestre');
	}

	return false;
}
