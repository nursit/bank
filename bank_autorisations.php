<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2015 - Distribue sous licence GNU/GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

// pipeline de chargement
function bank_autoriser($flux) {return $flux;}

function autoriser_bank_configurer($faire, $mode='', $id=0, $qui = NULL, $opt = NULL){
	return autoriser('webmestre');
}

function autoriser_utilisermodepaiement_dist($faire, $mode='', $id=0, $qui = NULL, $opt = NULL){
	include_spip("presta/$mode/config");
	$fonctions = array('autoriser_'.$mode.'_'.$faire,'autoriser_'.$mode.'_'.$faire.'_dist','autoriser_'.$mode,'autoriser_'.$mode.'_dist');
	foreach ($fonctions as $f) {
		if (function_exists($f)) {
			return $f($faire,$mode,$id,$qui,$opt);
		}
	}
	return true;
}

function autoriser_utilisermodepaiementabo_dist($faire, $mode='', $id=0, $qui = NULL, $opt = NULL){
	include_spip("presta/$mode/config");
	$fonctions = array('autoriser_'.$mode.'_'.$faire,'autoriser_'.$mode.'_'.$faire.'_dist','autoriser_'.$mode,'autoriser_'.$mode.'_dist');
	foreach ($fonctions as $f) {
		if (function_exists($f)) {
			return $f($faire,$mode,$id,$qui,$opt);
		}
	}
	return true;
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
function autoriser_transaction_encaissercheque_dist($faire, $type, $id_transaction, $qui, $opt) {
	return autoriser('webmestre');
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
function autoriser_transaction_rembourser_dist($faire, $type, $id_transaction, $qui, $opt) {
	return autoriser('webmestre');
}
