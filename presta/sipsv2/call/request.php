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

/**
 * Preparation de la requete par cartes
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param $config
 *   configuration du module
 * @return array
 */
function presta_sipsv2_call_request_dist($id_transaction, $transaction_hash, $config){

	include_spip('presta/sipsv2/inc/sipsv2');
	$mode = 'sipsv2';
	$logname = 'spipsvdeux';

	if (!is_array($config) OR !isset($config['type']) OR !isset($config['presta'])){
		spip_log("call_request : config invalide " . var_export($config, true), $logname . _LOG_ERREUR);
		$mode = $config['presta'];
	}
	$logname = str_replace(array('1', '2', '3', '4', '5', '6', '7', '8', '9'), array('un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'), $mode);

	$cartes = array('CB', 'VISA', 'MASTERCARD');
	if (isset($config['cartes']) AND $config['cartes']){
		$cartes = $config['cartes'];
	}
	$cartes_possibles = sipsv2_available_cards($config);

	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction) . " AND transaction_hash=" . sql_quote($transaction_hash))){
		spip_log("call_request : transaction $id_transaction / $transaction_hash introuvable", $logname . _LOG_ERREUR);
		return "";
	}

	if (!$row['id_auteur']
		AND isset($GLOBALS['visiteur_session']['id_auteur'])
		AND $GLOBALS['visiteur_session']['id_auteur']){
		sql_updateq("spip_transactions",
			array("id_auteur" => intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=" . intval($id_transaction)
		);
	}

	$billing = bank_porteur_infos_facturation($row);
	$mail = $billing['email'];

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$montant = intval(round(100*$row['montant'], 0));

	list($merchant_id, $key_version, $secret_key) = sipsv2_key($config);
	$service = $config['service'];

	//		Affectation des parametres obligatoires
	$parm = array();
	$parm['merchantId'] = $merchant_id;
	$parm['amount'] = $montant;
	$parm['currencyCode'] = "978";

	$parm['customerId'] = intval($row['id_auteur']) ? $row['id_auteur'] : $row['auteur_id'];
	$parm['orderId'] = intval($id_transaction);
	$parm['transactionReference'] = bank_transaction_id($row);
	//$parm['customerContact.email']=substr($mail,0,128);

	$parm['normalReturnUrl'] = bank_url_api_retour($config, 'response');
	//$parm['cancelReturnUrl']=bank_url_api_retour($config,'cancel');
	$parm['automaticResponseUrl'] = bank_url_api_retour($config, 'autoresponse');

	// pas de page de recu chez SIPS (not working)
	// $parm['bypassReceiptPage'] = 1;

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
		'action' => sipsv2_url_serveur($config),
		'hidden' => array(),
		'logo' => array(),
	);
	foreach ($cartes as $carte){
		if (isset($cartes_possibles[$carte])){
			$parm['paymentMeanBrandList'] = $carte;
			$contexte['hidden'][$carte] = sipsv2_form_hidden($config, $parm);
			$contexte['logo'][$carte] = $cartes_possibles[$carte];
		}
	}

	return $contexte;
}
