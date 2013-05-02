<?php
/*
 * Paiement
 * Commande, transaction, paiement
 *
 * Auteurs :
 * Cedric Morin, Yterium.com
 * (c) 2007-2009 - Distribue sous licence GNU/GPL
 *
 */

include_spip('presta/sips/inc/sips');
include_spip('inc/date');

// il faut avoir un id_transaction et un transaction_hash coherents
// pour se premunir d'une tentative d'appel exterieur
function presta_sips_call_response_dist(){

	include_spip('inc/config');
	$merchant_id = lire_config('bank_paiement/config_sips/merchant_id','');
	$service = lire_config('bank_paiement/config_sips/service','');
	$certif = lire_config('bank_paiement/config_sips/certificat','');

	// recuperer la reponse en post et la decoder
	$response = sips_response($service, $merchant_id, $certif);

	if ($response['merchant_id']!==$merchant_id) {
		spip_log('call_response : merchant_id invalide:'.sips_shell_args($response),'sips.'._LOG_ERREUR);
		return array(0,false);
	}

	return sips_traite_reponse_transaction($response);
}
?>