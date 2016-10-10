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


include_spip('presta/stripe/inc/stripe');

/**
 * Preparation de la requete par cartes
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param $config
 *   configuration du module
 * @param string $type
 *   type de paiement : acte ou abo
 * @return array
 */
function presta_stripe_call_request_dist($id_transaction, $transaction_hash, $config, $type="acte"){

	$mode = 'stripe';
	if (!is_array($config) OR !isset($config['type']) OR !isset($config['presta'])){
		spip_log("call_request : config invalide ".var_export($config,true),$mode._LOG_ERREUR);
		return "";
	}
	$mode = $config['presta'];

	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash))){
		spip_log("call_request : transaction $id_transaction / $transaction_hash introuvable",$mode._LOG_ERREUR);
		return "";
	}

	if (!$row['id_auteur']
	  AND isset($GLOBALS['visiteur_session']['id_auteur'])
	  AND $GLOBALS['visiteur_session']['id_auteur']) {
		sql_updateq("spip_transactions",
			array("id_auteur" => intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=" . intval($id_transaction)
		);
	}

	$email = bank_porteur_email($row);

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$montant = intval(round(100*$row['montant'],0));
	if (strlen($montant)<3)
		$montant = str_pad($montant,3,'0',STR_PAD_LEFT);

	include_spip('inc/filtres_mini'); // url_absolue

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
	);
	$contexte['sign'] = bank_sign_response_simple($config['presta'], $contexte);
	$hidden = "";
	foreach($contexte as $k=>$v){
		$hidden .= "<input type='hidden' name='$k' value='$v' />";
	}
	$contexte['hidden'] = $hidden;
	$contexte['action'] = bank_url_api_retour($config,"response");

	$contexte['email'] = $email;
	$contexte['amount'] = $montant;
	$contexte['currency'] = 'eur';
	$contexte['key'] = ($config['mode_test']?$config['PUBLISHABLE_KEY_test']:$config['PUBLISHABLE_KEY']);
	$contexte['name'] = textebrut($GLOBALS['meta']['nom_site']);
	$contexte['image'] = '';
	$contexte['description'] = _T('bank:titre_transaction') . '#'.$id_transaction;

	$chercher_logo = charger_fonction('chercher_logo','inc');
	if ($logo = $chercher_logo(0,'site')){
		$logo = reset($logo);
		$contexte['image'] = url_absolue($logo);
	}

	return $contexte;
}
