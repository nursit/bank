<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * Olivier Tétard
 * (c) 2014 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function presta_virement_payer_acte_dist($id_transaction,$transaction_hash) {
	return recuperer_fond(
		'presta/virement/payer/acte',
		array(
			'action' => generer_url_action('bank_response', 'bankp=virement', true, true),
			'id_transaction' => $id_transaction,
			'transaction_hash' => $transaction_hash,
			'attente_mode' => _request('attente_mode'),
		),
		array(
			'ajax'=>true
		)
	);
}
