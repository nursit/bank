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

function presta_simu_payer_abonnement_dist($id_transaction,$transaction_hash){

	return recuperer_fond(
		'presta/simu/payer/abonnement',
		array(
			'action'=>  generer_url_action('bank_response', 'bankp=simu&abo=oui',true,true),
			'id_transaction'=>$id_transaction,
			'transaction_hash'=>$transaction_hash
		)
	);
}

?>
