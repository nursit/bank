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

/**
 * Verifier le statut d'une transaction lors du retour de l'internaute
 *
 * @param array $response
 * @param string $mode
 * @return array
 */
function presta_sips_call_response_dist($response=null, $mode='sips'){

	include_spip('inc/bank');
	$config = bank_config($mode);

	include_spip('inc/config');
	$merchant_id = $config['merchant_id'];
	$service = $config['service'];
	$certif = $config['certificat'];

	// recuperer la reponse en post et la decoder
	if (is_null($response)){
		$response = sips_response($service, $merchant_id, $certif);
	}

	if ($response['merchant_id']!==$merchant_id) {
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "merchant_id invalide",
				'log' => sips_shell_args($response)
			)
		);
	}

	return sips_traite_reponse_transaction($response);
}
