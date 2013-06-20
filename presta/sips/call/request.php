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

// il faut avoir un id_transaction et un transacrion_hash coherents
// pour se premunir d'une tentative d'appel exterieur
function presta_sips_call_request_dist($id_transaction,$transaction_hash){
	$res = sql_select("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash));
	if (!$row = sql_fetch($res))
		return "";

	if (!$row['id_auteur'] AND !$row['auteur_id'] AND $GLOBALS['visiteur_session']['id_auteur'])
		sql_updateq("spip_transactions",array("id_auteur"=>$GLOBALS['visiteur_session']['id_auteur']),"id_transaction=".intval($id_transaction));

	// passage en centimes d'euros
	$montant = intval(100*$row['montant']);

	include_spip('inc/config');
	$merchant_id = lire_config('bank_paiement/config_sips/merchant_id','');
	$service = lire_config('bank_paiement/config_sips/service','');
	$certif = lire_config('bank_paiement/config_sips/certificat','');

	//		Affectation des parametres obligatoires
	$parm = array();
	$parm['merchant_id']=$merchant_id;
	$parm['merchant_country']="fr";
	$parm['currency_code']="978";
	$parm['amount']=$montant;
	$parm['customer_id']=intval($row['id_auteur'])?$row['id_auteur']:$row['auteur_id'];
	$parm['order_id']=intval($id_transaction);
	$parm['transaction_id']=modulo($row['transaction_hash'],999999);
	$parm['customer_email']=substr($row['auteur'],0,128);

	$parm['normal_return_url']=generer_url_action('bank_response',"bankp=sips",true,true);
	$parm['cancel_return_url']=generer_url_action('bank_cancel',"bankp=sips",true,true);
	$parm['automatic_response_url']=generer_url_action('bank_response',"bankp=sips",true,true);

	// ajouter les logos de paiement si configures
	foreach(array('logo_id','logo_id2','advert') as $logo_key){
		if ($file = lire_config('bank_paiement/config_sips/'.$logo_key,'')){
			$parm[$logo_key]=$file;
		}
	}


	//		Les valeurs suivantes ne sont utilisables qu'en pre-production
	//		Elles necessitent l'installation de vos fichiers sur le serveur de paiement
	//
	// 		$parm="$parm normal_return_logo=";
	// 		$parm="$parm cancel_return_logo=";
	// 		$parm="$parm submit_logo=";
	// 		$parm="$parm logo_id=";
	// 		$parm="$parm logo_id2=";
	// 		$parm="$parm advert=";
	// 		$parm="$parm background_id=";
	// 		$parm="$parm templatefile=";

	include_spip("presta/sips/inc/sips");
	$res = sips_request($service,$parm,$certif);
	$res['service'] = $service;

	return $res;
}
?>
