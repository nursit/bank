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

include_spip('inc/bank_devises');

function formulaires_demo_creer_transaction_charger_dist(){

	$devise_defaut = bank_devise_defaut();
	$valeurs = array(
		'montant' => '',
		'montant_ht' => '',
		'devise' => $devise_defaut['code'],
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

	$devise = _request('devise');
	$devises = bank_lister_devises();
	if (!isset($devises[$devise])) {
		$erreurs['devise'] = _L("Devise inconnue ! Installez le plugin intl ?");
	}

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

	$montant = _request('montant');
	$options = array(
		'montant_ht' => _request('montant_ht'),
		'devise' => _request('devise') ?: 'EUR',
		'id_auteur' => $id_auteur,
		'auteur_id' => _request('auteur_id'),
		'auteur' => _request('auteur'),
		'parrain' => _request('parrain'),
		'tracking_id' => _request('tracking_id'),
	);

	$id_transaction = $inserer_transaction($montant, $options);

	if ($id_transaction){
		return array('message_ok' => "Transaction $id_transaction cree", 'editable' => true);
	} else {
		return array('message_erreur' => "Echec creation de la transaction", 'editable' => true);
	}
}
