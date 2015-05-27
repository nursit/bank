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

include_spip('presta/ogone/inc/ogone');

/**
 * Generer le contexte pour le formulaire de requete de paiement
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param array $config
 * @param null|array $response
 * @return array
 */
function presta_ogone_call_response_dist($config, $response=null){

	include_spip('inc/bank');
	$mode = $config['presta'];

	if (!$response)
		// recuperer la reponse en post et la decoder
		$response = ogone_get_response($config);
	#var_dump($response);
	if (!$response) {
		return array(0,false);
	}

	// depouillement de la transaction
	list($id_transaction,$success) =  ogone_traite_reponse_transaction($config, $response);

	return array($id_transaction,$success);	
}
