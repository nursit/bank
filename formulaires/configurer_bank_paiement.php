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

include_spip('inc/bank');

function formulaires_configurer_bank_paiement_verifier_dist(){
	$erreurs = array();
	if ($e = _request('email_ticket_admin') AND !email_valide($e))
		$erreurs['email_ticket_admin'] = _T('form_prop_indiquer_email');

	return $erreurs;
}


?>
