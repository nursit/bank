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


/**
 * @param array $config
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param array $options
 * @return array|string
 */
function presta_simu_payer_abonnement_dist($config, $id_transaction, $transaction_hash, $options=array()){

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
	);
	$contexte['sign'] = bank_sign_response_simple('simu', $contexte);

	// url action
	$action = bank_url_api_retour($config,'response');
	foreach($contexte as $k=>$v){
		$action = parametre_url($action,$k,$v);
	}
	$action = parametre_url($action,"abo","oui");
	$contexte['action'] = $action;

	$contexte = array_merge($options, $contexte);

	return recuperer_fond('presta/simu/payer/abonnement',$contexte);
}
