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

include_spip('presta/payzen/inc/payzen');

/**
 * Jamais appele directement dans le plugin bank/
 * mais par une eventuelle methode abos/resilier d'un plugin externe
 *
 * @param string $uid
 * @param array|string $config
 * @return bool
 */
function presta_stripe_call_resilier_abonnement_dist($uid, $config = 'stripe'){

	include_spip('inc/bank');

	$trans = sql_fetsel("mode, pay_id", "spip_transactions", "abo_uid=" . sql_quote($uid) . " AND mode LIKE " . sql_quote($config . '%'),'','id_transaction','0,1');

	if (!is_array($config)){
		$config = bank_config($trans['mode']);
	}
	$mode = $config['presta'];

	// charger l'API Stripe avec la cle
	stripe_init_api($config);

	$erreur = $erreur_code = '';
	try {
		if ($sub = \Stripe\Subscription::retrieve($uid)) {
			$res = $sub->cancel();
			if ($res->status != 'canceled'){
				$erreur = 'cancel failed' . var_export((array)$res, true);
			}
		}
		else {
			$erreur = "unknown subscription";
		}
	}
	catch (Exception $e) {
		if ($body = $e->getJsonBody()){
			$err  = $body['error'];
			list($erreur_code, $erreur) = stripe_error_code($err);
		}
		else {
			$erreur = $e->getMessage();
			$erreur_code = 'error';
		}
	}


	if ($erreur or $erreur_code) {
		spip_log($s="call_resilier_abonnement $uid : erreur $erreur_code - $erreur",$mode._LOG_ERREUR);
		return false;
	}

	return true;
}