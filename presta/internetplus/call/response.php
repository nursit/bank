<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2014 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;


/**
 * @param array $config
 * @return array
 */
function presta_internetplus_call_response_dist($config){

	include_spip('inc/bank');
	$mode = $config['presta'];

	$traiter_reponse = charger_fonction('traiter_reponse','presta/internetplus/inc');
	list($id_transaction,$result,$mp)=$traiter_reponse($config);

	// pour gerer les redirections dans le pipeline si besoin
	$_SESSION['wha_mp'] = $mp;
	return array($id_transaction,$result);
}