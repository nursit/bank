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
 * @param array $config
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param array $options
 * @return array|string
 */
function presta_stripe_payer_acte_dist($config, $id_transaction, $transaction_hash, $options = array()){

	$call_request = charger_fonction('request', 'presta/stripe/call');
	$contexte = $call_request($id_transaction, $transaction_hash, $config);

	// si moyen de paiement pas applicable
	if (!$contexte){
		return '';
	}

	$contexte['sandbox'] = ($config['mode_test'] ? ' ' : '');
	$contexte['config'] = $config;

	// logo multi moyen de paiements
	$cartes = array('card');
	if (isset($config['cartes']) AND $config['cartes']){
		$cartes = $config['cartes'];
	}
	$c = $config;
	$c['type'] = 'acte';
	$cartes_possibles = stripe_available_cards($c);

	$logo = [];
	foreach ($cartes_possibles as $card => $logo_this_card) {
		if (in_array($card, $cartes)) {
			$img = bank_trouver_logo("stripe", $logo_this_card);
			$logo[] = bank_label_bouton_img_ou_texte($img, bank_label_payer_par_carte($card));
			if ($card !== 'card' and empty($options['payer_par_title'])) {
				$contexte['payer_par_title'] = _T('bank:payer_par_moyen_securise');
			}
		}
	}

	$contexte['logo'] = bank_trouver_logo("stripe", 'CARD.gif'); // compat si ancien modele surcharge
	$contexte['logos'] = implode('<span class="sep"> | </span>', $logo);

	$contexte = array_merge($options, $contexte);

	return recuperer_fond('presta/stripe/payer/acte', $contexte);
}

