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

/* Stripe  ----------------------------------------------------------- */



function stripe_lister_cartes_config($c, $cartes = true){
	include_spip('inc/bank');
	$config = array('presta' => 'stripe', 'type' => isset($c['type']) ? $c['type'] : 'acte');

	include_spip("presta/stripe/inc/stripe");
	$liste = stripe_available_cards($config);

	return $liste;
}

/**
 * Action pour déclencher le checkout session chez Stripe quand l'utilisateur a cliqué sur le bouton "payer par Stripe"
 * (et non avant)
 *
 * @return void
 */
function action_stripe_process_checkout_dist() {
	$securiser_action = charger_fonction('securiser_action', 'inc');
	$arg = $securiser_action();

	$action = base64_decode($arg, true);
	$action = str_replace("&amp;", "&", $action);
	if ($action
	  and $id_transaction = parametre_url($action, 'id_transaction')
	  and $transaction_hash = parametre_url($action, 'transaction_hash')
	  and $sign = parametre_url($action, 'sign')) {

		$contexte = [
			'id_transaction' => $id_transaction,
			'transaction_hash' => $transaction_hash,
			'sign' => $sign,
		];
		if (!is_null($abo = parametre_url($action, 'abo'))) {
			$contexte['abo'] = $abo;
		}

		if (strpos($action, '/bank.api/') !== false) {
			$arg_api = explode('/bank.api/', $action, 2);
			$arg_api = end($arg_api);
			$arg_api = explode('/', $arg_api);
			$presta = reset($arg_api);
		}
		else {
			$presta = parametre_url($action, 'bankp');
		}
		include_spip('inc/bank');
		$config = bank_config($presta);
		$mode = '';
		if ($config) {
			$mode = $config['presta'];
			if (isset($config['mode_test']) AND $config['mode_test']){
				$mode .= "_test";
			}
		}
		if ($mode and bank_response_simple($mode, $contexte)) {
			$quoi = ((isset($contexte['abo']) ? $contexte['abo'] : false) ? 'abo' : 'acte');
			$call_request = charger_fonction('request', 'presta/stripe/call');
			$contexte = $call_request($id_transaction, $transaction_hash, $config, $quoi, ['process_checkout' => true]);
			$checkout_session_id = $contexte['checkout_session_id'];
			$redirect = _request('redirect');
			// et on finit le hit ajax avec le checkout_session_id en plus
			$redirect = parametre_url($redirect, 'checkout_session_id', $checkout_session_id, '&');
			$redirect = parametre_url($redirect, 'autosubmit', 1, '&');
			$GLOBALS['redirect'] = $redirect;
		}
		else {
			$mode = ($mode ?: 'stripe');
			spip_log("action_stripe_process_checkout_dist: action corrompue '$action' impossible de traiter la demande", $mode . _LOG_ERREUR);
		}
	}
}
