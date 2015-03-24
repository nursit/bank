<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('presta/cyberplus/inc/cyberplus');

// il faut avoir un id_transaction et un transaction_hash coherents
// pour se premunir d'une tentative d'appel exterieur
function presta_cyberplus_call_response_dist($response=null, $mode='cyberplus'){

	include_spip('inc/bank');
	$config = bank_config($mode);

	if (!$response){
		// recuperer la reponse en post et la decoder
		$response = cyberplus_recupere_reponse($config);
	}

	if (!$response) {
		return array(0,false);
	}

	// depouillement de la transaction
	list($id_transaction,$success) =  cyberplus_traite_reponse_transaction($response,$mode);

	return array($id_transaction,$success);
}
?>
