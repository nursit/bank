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
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * Clic&Pay utilise le meme code que PayZen qui est l'implementation de reference Lyra Networks
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_clicandpay_call_response_dist($config, $response = null){

	$call_response = charger_fonction("response", "presta/payzen/call");
	return $call_response($config, $response);
}
