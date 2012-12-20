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

function formulaires_configurer_bank_paiement_charger_dist(){
	$config = unserialize($GLOBALS['meta']['bank_paiement']);
	$valeurs = array(
		'presta_simu' => $config['presta']['simu'],
		'presta_ogone' => $config['presta']['ogone'],
		'presta_paybox' => $config['presta']['paybox'],
		'presta_paypal' => $config['presta']['paypal'],
		'presta_paypal_express' => $config['presta']['paypal_express'],
		'presta_sips' => $config['presta']['sips'],
		'presta_wha' => $config['presta']['wha'],
		'presta_cheque' => $config['presta']['cheque'],
		'presta_abo_paybox' => $config['presta']['abo_paybox'],
		'presta_abo_wha' => $config['presta']['abo_wha'],
		'presta_cmcic' => $config['presta']['cmcic'],
		'email_ticket_admin' => $config['email_ticket_admin'],
	);
	
	return $valeurs;
}

function formulaires_configurer_bank_paiement_verifier_dist(){
	$erreurs = array();
	if ($e = _request('email_ticket_admin') AND !email_valide($e))
		$erreurs['email_ticket_admin'] = _T('form_prop_indiquer_email');

	return $erreurs;
}

function formulaires_configurer_bank_paiement_traiter_dist(){
	$config = array();
	$valeurs = formulaires_configurer_bank_paiement_charger_dist();

	include_spip('inc/meta');
	foreach ($valeurs as $k=>$v){
		if (!is_null(_request($k))) {
			if (preg_match(',^presta_(.*)$,',$k,$r))
				$config['presta'][$r[1]] = _request($k);
			else
				$config[$k] = _request($k);
		}
	}
	ecrire_meta('bank_paiement',serialize($config));

	// mettre a jour la config des banques
	include_spip('base/bank_install');
	bank_presta_install();
	
	return array('message_ok'=>_T('config_info_enregistree'),'editable'=>true);
}

?>
