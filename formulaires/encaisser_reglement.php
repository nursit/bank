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
 * (c) 2012-2015 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function formulaires_encaisser_reglement_charger_dist($id_transaction, $mode){

	$prestas = array();
	if (isset($GLOBALS['meta']['bank_paiement'])
		AND $config = unserialize($GLOBALS['meta']['bank_paiement'])){

		$prestas = (is_array($config['presta'])?$config['presta']:array());
		$prestas = array_filter($prestas);
		if (is_array($config['presta_abo']))
			$prestas = array_merge($prestas,array_filter($config['presta_abo']));
	}

	if (!((isset($prestas[$mode]) AND $prestas[$mode]) OR $mode=='gratuit'))
		return false;

	$valeurs = array(
		'_id_transaction' => $id_transaction,
		'_mode' => $mode,
		'autorisation_id'=>'',
		'_autorisation_id_suffixe' => "/".autorisation_suffixe(),
	);
	
	return $valeurs;
}

function formulaires_encaisser_reglement_verifier_dist($id_transaction, $mode){
	$erreurs = array();
	$autorisation_id = _request('autorisation_id');
	$max_len = 55-strlen(autorisation_suffixe())-1;
	if (!$autorisation_id){
		$erreurs['autorisation_id'] = _T('info_obligatoire');
	}
	elseif(strlen($autorisation_id) AND $max_len>0 AND strlen($autorisation_id)>$max_len){
		$erreurs['autorisation_id'] = _L($max_len.' car. maximum').' ('.strlen($autorisation_id).')';
	}
	return $erreurs;
}

function formulaires_encaisser_reglement_traiter_dist($id_transaction, $mode){

	$hash = sql_getfetsel("transaction_hash","spip_transactions","id_transaction=".intval($id_transaction));
	$autorisation_id = autorisation_suffixe();
	if (strlen($autorisation_id)<55){
		$autorisation_id = _request('autorisation_id')."|".$autorisation_id;
	}

	include_spip('inc/bank');
	$response = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $hash,
		'autorisation_id' => $autorisation_id,
	);
	$sign = bank_sign_response_simple($mode,$response);
	foreach($response as $k=>$v){
		set_request($k,$v);
	}
	set_request("sign",$sign);
	set_request("bankp",$mode);

	// on charge l'action et on l'appelle pour passer par tout le processus de paiement standard
	$bank_response = charger_fonction("bank_response","action");
	return array('message_ok'=>$bank_response());
}

function autorisation_suffixe(){
	return "#".$GLOBALS['visiteur_session']['id_auteur']."-".$GLOBALS['visiteur_session']['nom'];
}