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
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('presta/sipsv2/inc/sipsv2');
include_spip('inc/date');

/**
 * Verifier le statut d'une transaction lors du retour de l'internaute
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_sipsv2_call_response_dist($config, $response=null){

	include_spip('inc/bank');
	$mode = $config['presta'];

	// recuperer la reponse en post et la decoder
	if (is_null($response)){
		$response = sipsv2_recupere_reponse($config);
	}

	$merchant_id = $config['merchant_id'];
	if (!isset($response['Data']['merchantId'])
		or $response['Data']['merchantId']!==$merchant_id) {
		return bank_transaction_invalide(0,
			array(
				'mode' => $mode,
				'erreur' => "merchantId invalide",
				'log' => bank_shell_args($response)
			)
		);
	}

	return sipsv2_traite_reponse_transaction($config, $response);
}
