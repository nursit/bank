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

function presta_internetplus_inc_traiter_reponse_dist($mode='wha'){
	$id_transaction = 0;
	if (!$m = _request('m')) return array($id_transaction,false,false);
	$m = urldecode($m);
	$mp = false;
	
	if (!$decode=wha_unsign($m)) {
		spip_log($t = "wha_traiter_reponse : signature invalide : $m",$mode);
		// on log ca dans un journal dedie
		spip_log($t,'wha_douteux');
		// on mail le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail','inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'],'[WHA]Transaction Frauduleuse',$t,"sips@".$_SERVER['HTTP_HOST']);
		return array($id_transaction,false,false);
	}
	list($unsign,$partnerId,$keyId) = $decode;
	#var_dump($unsign);
	$args = wha_extract_args($unsign);
	
	$mp = $args['v']['mp'];
	#var_dump($args);
	// recuperer le code de resultat
	$c = isset($args['c'])?$args['c']:"";
	// annulation de l'internaute
	if (preg_match(",^(OfferAuthorization|Authorize)Cancel$,i",$c)){
		spip_log($t = "wha_traiter_reponse : annulation de la transaction : $m",$mode);
		if (isset($args['v'])
		 AND is_array($mp=$v=$args['v'])
		 AND $id_transaction=intval($v['id_transaction'])) {
			$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction));
			if ($row['reglee']=='oui') return array($id_transaction,true,$mp);
			// sinon enregistrer echec transaction
			$date_paiement = date('Y-m-d H:i:s');
			include_spip('inc/bank');
			bank_echec_transaction($id_transaction,$mode,$date_paiement,"","Annulation",var_export($args,true));
		}
		return array($id_transaction,false,$mp);		
	}
	
	// Code inconnu : on ne fait rien ?
	if (!preg_match(",^(OfferAuthorization|Authorize)Success$,i",$c)) {
		spip_log($t = "wha_traiter_reponse : code reponse c inconnu, traitement impossible : $m",$mode);
		return array($id_transaction,false,$mp);
	}

	// Numero de transaction inconnue : on ne fait rien
	if (!isset($args['v'])
	 OR !is_array($v=$args['v'])
	 OR !isset($v['mp'])
	 OR !is_array($mp = $v['mp'])){
		spip_log($t = "wha_traiter_reponse : traitement impossible : $m",$mode);
		return array($id_transaction,false,$mp);
	}

	// OK
	$traiter_reponse = charger_fonction("traiter_reponse_$mode",'presta/internetplus/inc');
	return $traiter_reponse($m,$args,$partnerId,$keyId);
}

?>