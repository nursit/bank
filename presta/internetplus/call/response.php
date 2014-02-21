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


function presta_internetplus_call_response_dist(){

	$wha_traiter_reponse = charger_fonction('traiter_reponse','presta/internetplus/inc');
	list($id_transaction,$result,$mp)=$wha_traiter_reponse(_request('abo')?'wha_abo':'wha');

	// pour gerer les redirections dans le pipeline si besoin
	$_SESSION['wha_mp'] = $mp;
	return array($id_transaction,$result);
}