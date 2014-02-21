<?php
/*
 * Paiement
 * Commande, transaction, paiement
 *
 * Auteurs :
 * Cedric Morin, Yterium.com
 * (c) 2007 - Distribue sous licence GNU/GPL
 *
 */
include_spip('wha/wha_services');

function wha_check_offer_dist($id_abonnement){
	$res = spip_query('SELECT * FROM spip_abonnements WHERE id_abonnement='.intval($id_abonnement));
	if (!$row = spip_fetch_array($res))
	  return false;
	
	if ($row['mode_paiement']!='wha'
	  OR !$uoid = $row['abonne_uid']){
		spip_log("wha_check_offer :Erreur : abonnement $id_abonnement n'a pas ete souscrit avec wha (ou pas d'uoid)",'wha_abo_check');
		return false;
	}
	if (!$confirm = $row['confirm']
	 OR !$confirm=unserialize($confirm)) {
		spip_log("wha_check_offer :Erreur : abonnement $id_abonnement n'a pas d'url node enregistree",'wha_abo_check');
		return false;
	}
	
	$url_check = wha_url_check_abo($uoid,'love',$confirm['partner'],$confirm['key'],$confirm['node']);
	
	include_spip('inc/distant');
	$ack = @recuperer_page($url_check);
	spip_log($t = "wha_check_offer : reponse a $url_check : $ack",'wha_abo_check');
	if (!$ack
	  OR (!$unsign=wha_unsign($ack))
	  OR (!$args=wha_extract_args(reset($unsign)))
	  ){
		spip_log($t = "wha_check_offer : pas de reponse valide $url_check : $ack",'wha_abo_check');
		return null;
	}
	if ((isset($args['c']))
	  AND ($args['c']=='ack'))
	  return true;
	  
	if ((isset($args['e']))
	  AND (in_array($args['e'],array(0,1,14,15))))
	  return false;

	return null;
}

?>