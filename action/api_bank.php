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

function action_api_bank_dist(){
	$arg = _request('arg');

	// arg a ete ajoute par le rewrite rule, donc non pris en compte dans une eventuelle signature
	// on l'enleve
	unset($_REQUEST['arg']);
	unset($_POST['arg']);
	unset($_GET['arg']);

	$arg = explode('/',$arg);
	$presta = array_shift($arg);
	$action = array_shift($arg);
	if (!in_array($action,array('response','cancel','autoresponse')))
		$action = 'response';

	$auto = ($action=='autoresponse'?true:false);
	$cancel = ($action=='cancel'?true:false);

	$bank_response = charger_fonction('bank_response','action');
	$bank_response($cancel, $auto, $presta);
}