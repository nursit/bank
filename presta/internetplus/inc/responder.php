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

function wha_responder_dist($mode='wha_responder'){
	$uoid = 0;
	if (!$m = _request('m')) return array($uoid,false);
	$m = urldecode($m);
	if (!$decode=wha_unsign($m)) {
		spip_log($t = "wha_responder : signature invalide : $m",$mode);
		// on log ca dans un journal dedie
		spip_log($t,'wha_douteux');
		return array($uoid,false);
	}
	list($unsign,$partnerId,$keyId) = $decode;
	#var_dump($unsign);
	$args = wha_extract_args($unsign);
	#var_dump($args);
	// recuperer le code de resultat
	$c = isset($args['c'])?$args['c']:"";
	
	// annulation de l'internaute
	if (!preg_match(",^(NMPOC_NEW)$,i",$c)){
		spip_log($t = "wha_responder : c invalide : $m",$mode);
		return array($uoid,false);		
	}

	// OK
	if (!isset($args['v'])
	 OR !is_array($v=$args['v'])
	 OR !isset($v['r'])
	 OR !isset($v['uo'])
	 ){
		spip_log($t = "wha_responder : traitement impossible : $m",$mode);
		return array($uoid,false);
	}
	$uoid = $v['uo'];
	

	// envoyer l'acquitement
	echo wha_message("ack",false,$partnerId,$keyId);
	// (id_abonnement, (immediat,commentaire))
	return array($uoid,array($v['r']!=200,$v['r'].':'.$v['c']));
}

?>