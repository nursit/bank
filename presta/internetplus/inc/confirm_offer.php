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
include_spip('presta/internetplus/inc/wha_services');

function presta_internetplus_inc_confirm_offer_dist($id_transaction,$uoid, $confirm){


	$url_confirm = wha_url_confirm_abo($uoid,$confirm['partner'],$confirm['key'],$confirm['node']);
	
	include_spip('inc/distant');
	$ack = @recuperer_page($url_confirm);
	if (!$ack
	  OR (!$unsign=wha_unsign($ack))
	  OR (!$args=wha_extract_args(reset($unsign)))
	  OR (!isset($args['c']))
	  OR (!$args['c']=='ack')) {
		spip_log($t = "wha_confirm_offer : transaction $id_transaction, echec confirmation de debit $uoid / $url_confirm : $ack",'internetplus_abo'._LOG_ERREUR);
		spip_log($t,'internetplus_abo_hs'._LOG_ERREUR);

		return false;
	}

	return true;

}

?>