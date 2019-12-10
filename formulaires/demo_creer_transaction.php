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

function formulaires_demo_creer_transaction_charger_dist(){
	$valeurs = array(
		'montant' => '',
		'montant_ht' => '',
		'id_auteur' => '',
		'auteur_id' => '',
		'auteur' => '',
		'parrain' => '',
		'tracking_id' => '',
	);

	return $valeurs;
}

function formulaires_demo_creer_transaction_verifier_dist(){
	$erreurs = array();
	return $erreurs;
}

function formulaires_demo_creer_transaction_traiter_dist(){
	$inserer_transaction = charger_fonction('inserer_transaction', 'bank');
	$id_auteur = _request('id_auteur');
	if (!strlen($id_auteur)
		and !_request('auteur_id')
		and isset($GLOBALS['visiteur_session']['id_auteur'])){
		$id_auteur = $GLOBALS['visiteur_session']['id_auteur'];
	}
	$id_transaction = $inserer_transaction(
		_request('montant'),
		_request('montant_ht'),
		$id_auteur,
		_request('auteur_id'),
		_request('auteur'),
		_request('parrain'),
		_request('tracking_id'));

	if ($id_transaction){
		return array('message_ok' => "Transaction $id_transaction cree", 'editable' => true);
	} else {
		return array('message_erreur' => "Echec creation de la transaction", 'editable' => true);
	}
}

