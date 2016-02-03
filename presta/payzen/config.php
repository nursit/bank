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

/* PayZen  ----------------------------------------------------------- */


/**
 *
 * Payzen est fourni par LyraNetworks comme SystemPay dont il partage une grande partie du code
 *
 */

# Version du logiciel
if (!defined('_SYSTEMPAY_VERSION'))
	define("_SYSTEMPAY_VERSION", "V2");


function payzen_lister_cartes_config($c,$cartes = true){
	include_spip('inc/bank');
	$config = array('presta'=>'payzen','type'=>isset($c['type'])?$c['type']:'acte','service'=>'payzen');

	include_spip("presta/systempay/inc/systempay");
	$liste = systempay_available_cards($config);

	$others = array('SDD','E_CV');
	foreach($liste as $k=>$v){
		if ($cartes AND in_array($k,$others)){
			unset($liste[$k]);
		}
		if (!$cartes AND !in_array($k,$others)){
			unset($liste[$k]);
		}
	}
	return $liste;
}


/**
 * Titre "paiement SEPA" eventuel
 * @param $mode
 * @param $id_transaction
 * @return mixed|string
 */
function presta_payzen_titre_type_paiement_dist($mode, $id_transaction) {

	if ($id_transaction
	  AND $trans = sql_fetsel("refcb","spip_transactions","id_transaction=".intval($id_transaction))
	  AND strncmp($trans['refcb'],"SEPA",4)==0){
		return _T("bank:label_type_paiement_sepa",array('presta'=>"Payzen"));
	}

	return "";
}
