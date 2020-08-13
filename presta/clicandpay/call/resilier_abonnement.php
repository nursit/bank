<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * Lyra
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('presta/clicandpay/inc/clicandpay');

/**
 * Jamais appele directement dans le plugin bank/
 * mais par une eventuelle methode abos/resilier d'un plugin externe
 *
 * Clic&Pay utilise le meme code que PayZen qui est l'implementation de reference Lyra Networks
 *
 * @param string $uid
 * @param array|string $config
 * @return bool
 */
function presta_clicandpay_call_resilier_abonnement_dist($uid, $config = 'clicandpay'){

	$call_resilier_abonnement = charger_fonction("call_resilier_abonnement", "presta/payzen/call");
	return $call_resilier_abonnement($uid, $config);
}
