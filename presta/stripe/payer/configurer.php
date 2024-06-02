<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Laurent Lefebvre largement inspiré du code de Cedric Morin / Nursit.com
 * (c) 2012-2024 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * @param array $config
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param array $options
 * @return array|string
 */
function presta_stripe_payer_configurer_dist($id_transaction,$th){
	$call = charger_fonction('modifier_paiement','presta/stripe/call');
	$contexte = $call($id_transaction, $th);
	// si moyen de paiement pas applicable
	if (!$contexte) {
		return '';
	}

	$contexte['id_transaction'] = $id_transaction;
	$contexte['transaction_hash'] = $th;

	return recuperer_fond('presta/stripe/payer/configurer', $contexte);
}

