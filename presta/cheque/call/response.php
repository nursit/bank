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


/**
 * Call response simple (cheque, virement)
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param null|array $response
 * @param string $mode
 * @return array
 */
function presta_cheque_call_response_dist($response=null, $mode="cheque"){

	include_spip('inc/bank');
	return bank_simple_call_response($response,$mode);
}
