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

function formulaires_encaisser_reglement_charger_dist($id_transaction, $config){

	if (!$config){
		return false;
	}
	include_spip('inc/bank');
	if (is_string($config)){
		$config = bank_config($config);
	}

	if (!$config OR $config['presta']=='gratuit'){
		return false;
	}
	$mode = $config['presta'];

	if (!$transaction = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction))){
		return false;
	}

	$valeurs = array(
		'_id_transaction' => $id_transaction,
		'_mode' => $mode,
		'autorisation_id' => '',
		'montant' => affiche_monnaie($transaction['montant'], 2, false),
		'_autorisation_id_suffixe' => "/" . autorisation_suffixe(),
	);

	if ($transaction['reglee']==='oui'){
		// si c'est un POST on met juste editable=false pour afficher le message ok et la redirection si besoin
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$valeurs['editable'] = false;
		}
		else {
			return false;
		}
	}

	return $valeurs;
}

function formulaires_encaisser_reglement_verifier_dist($id_transaction, $config){
	$erreurs = array();
	$autorisation_id = _request('autorisation_id');
	$max_len = 55-strlen(autorisation_suffixe())-1;
	if (!$autorisation_id){
		$erreurs['autorisation_id'] = _T('info_obligatoire');
	} elseif (strlen($autorisation_id) AND $max_len>0 AND strlen($autorisation_id)>$max_len) {
		$erreurs['autorisation_id'] = _L($max_len . ' car. maximum') . ' (' . strlen($autorisation_id) . ')';
	}

	$montant = _request('montant');
	if ($montant = recuperer_montant_saisi($montant)){
		$transaction = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction));

		if (intval($transaction['montant']*1000)!==intval($montant*1000)){
			if (!_request('confirmer_montant') or _request('confirmer_montant')!=$montant){
				$erreurs['montant'] = "<label><input type='checkbox' value='{$montant}' name='confirmer_montant' /> " . _T('bank:erreur_confirmer_montant_reglement_different') . "</label>";
				set_request('montant', $montant);
				$erreurs['message_erreur'] = '';
			}
		} else {
			set_request('montant');
		}
	} else {
		set_request('montant');
	}

	return $erreurs;
}

function recuperer_montant_saisi($montant){
	$montant = str_replace(",", ".", trim($montant));
	$montant = preg_replace(",[^\d.-],", "", $montant);
	return $montant;
}

function formulaires_encaisser_reglement_traiter_dist($id_transaction, $config){

	include_spip('inc/bank');
	if (is_string($config)){
		$config = bank_config($config);
	}
	$mode = $config['presta'];

	$hash = sql_getfetsel("transaction_hash", "spip_transactions", "id_transaction=" . intval($id_transaction));
	$autorisation_id = autorisation_suffixe();
	if (strlen($autorisation_id)<55){
		$autorisation_id = _request('autorisation_id') . "|" . $autorisation_id;
	}

	include_spip('inc/bank');
	$response = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $hash,
		'autorisation_id' => $autorisation_id,
	);

	$montant = _request('montant');
	if ($montant = recuperer_montant_saisi($montant)){
		$response['montant'] = $montant;
	}

	$sign = bank_sign_response_simple($mode, $response);
	foreach ($response as $k => $v){
		set_request($k, $v);
	}
	set_request("sign", $sign);
	set_request("bankp", $mode . "-" . bank_config_id($config));

	// on charge l'action et on l'appelle pour passer par tout le processus de paiement standard
	$bank_response = charger_fonction("bank_response", "action");
	return array('message_ok' => $bank_response());
}

function autorisation_suffixe(){
	return "#" . $GLOBALS['visiteur_session']['id_auteur'] . "-" . $GLOBALS['visiteur_session']['nom'];
}