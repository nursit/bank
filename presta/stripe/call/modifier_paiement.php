<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Laurent Lefebvre largement inspiré du code de Cedric Morin / Nursit.com
 * (c) 2012-2024 - Distribue sous licence GNU/GPL
 *
 */

use Stripe\Exception\ApiErrorException;

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('presta/stripe/inc/stripe');

/**
 * Preparation de modification de cartes
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
function presta_stripe_call_modifier_paiement_dist($id_transaction, $transaction_hash){

	include_spip('inc/bank');
	$mode = 'stripe';
	$config = bank_config('stripe','setup');
	if (isset($config['mode_test']) AND $config['mode_test']){
		$mode .= "_test";
	}

	// Coté Spip : Recherche de transaction
	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction) . " AND transaction_hash=" . sql_quote($transaction_hash))){
		spip_log("call_modifier_paiement : transaction $id_transaction / $transaction_hash introuvable",$mode._LOG_ERREUR);
		return false;
	}
	// Coté Spip : Recherche d'auteur
	if ($auteur = sql_fetsel("*","spip_auteurs","id_auteur=".intval($row['id_auteur']))){
		// spip_log("call_modifier_paiement : auteur ". $auteur['id_auteur'],$mode._LOG_DEBUG);
		if( !isset($GLOBALS['visiteur_session']['id_auteur'])
		  OR $GLOBALS['visiteur_session']['id_auteur']!=$auteur['id_auteur']) {
				spip_log("call_modifier_paiement : Seul l'auteur spécifié peut modifier son moyen de paiement ",$mode._LOG_ERREUR);
				return "";
	  }
	} else {
		spip_log("call_modifier_paiement : auteur introuvable ",$mode._LOG_ERREUR);
		return "";
	}

	$billing = bank_porteur_infos_facturation($auteur);
	$email = $billing['email'];
	// Variables qui vont être récupérés en POST
	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
	);
	$contexte['sign'] = bank_sign_response_simple($mode, $contexte);
	$url_success = bank_url_api_retour($config,"response");
	$url_cancel = bank_url_api_retour($config,"cancel");
	foreach($contexte as $k=>$v){
		$url_success = parametre_url($url_success, $k, $v, '&');
		$url_cancel = parametre_url($url_cancel, $k, $v, '&');
	}

	$contexte['action'] = str_replace('&', '&amp;', $url_success);
	$contexte['email'] = $email;
	$contexte['key'] = ($config['mode_test'] ? $config['PUBLISHABLE_KEY_test'] : $config['PUBLISHABLE_KEY']);
	$contexte['name'] = bank_nom_site();
	$contexte['description'] = _T('bank:titre_transaction') . '#' . $id_transaction;
	$contexte['image'] = find_in_path('img/logo-paiement-stripe.png');

	stripe_init_api($config);
	stripe_set_webhook($config);

	//  ================== CUSTOMER ===================

	// Coté Stripe : on vérifie que le customer existe
	if ($row['id_auteur']) {
		$config_id = bank_config_id($config);
		$customer_id = sql_getfetsel('pay_id', 'spip_transactions',
			'pay_id!=' . sql_quote('') . ' AND id_auteur=' . intval($row['id_auteur']) . ' AND statut=' . sql_quote('ok') . ' AND mode=' . sql_quote("$mode/$config_id"),
			'', 'date_paiement DESC', '0,1');
		if ($customer_id
			and $customer = stripe_customer($mode, ['id' => $customer_id])
			and $customer->email === $contexte['email']
		){
			spip_log("call_modifier_paiement : Customer retrouvé :". $id_customer,$mode._LOG_DEBUG);
			$checkout_customer_id = $customer_id;
		} else {
			spip_log("call_modifier_paiement : ce Customer n'existe pas chez Stripe". $id_customer,$mode._LOG_ERREUR);
			return false;
		}
		if (!$checkout_customer_id
		  and $customer = stripe_customer($mode, ['email' => $contexte['email'], 'nom' => trim($billing['prenom'] . ' ' . $billing['nom'])])){
			$checkout_customer_id = $customer->id;
		}
	}

	//  On prépare la sessions avec les données génériques
	$session_desc = [
		'payment_method_types' => ['card'],
		'success_url' => $url_success . '&session_id={CHECKOUT_SESSION_ID}',
		'cancel_url' => $url_success, // on revient sur success aussi car response gerera l'echec du fait de l'absence de session_id
		'locale' => stripe_locale($GLOBALS['spip_lang']),
		'customer_email' => $email,
	];

	// Et on déclare les paramètres particuliers du mode "setup" de la session checkout.
	$session_desc['mode'] = 'setup';
	$session_desc[	'setup_intent_data']['description'] = 'Enregistrement de nouveau moyen de paiement';
	$session_desc[	'setup_intent_data']['metadata']['customer_id'] = $id_customer;
	$session_desc[	'setup_intent_data']['metadata']['subscription_id'] = str_replace('/','',$row['abo_uid']);

	$session = \Stripe\Checkout\Session::create($session_desc);
	$contexte['checkout_session_id'] = $session->id;

	return $contexte;

}