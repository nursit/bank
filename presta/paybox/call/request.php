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


include_spip('presta/paybox/inc/paybox');
// il faut avoir un id_transaction et un transaction_hash coherents
// pour se premunir d'une tentative d'appel exterieur
function presta_paybox_call_request_dist($id_transaction, $transaction_hash, $abo=false, $cartes=array('CB','VISA','EUROCARD_MASTERCARD','E_CARD')){
	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash)))
		return "";

	if (!$row['id_auteur'] AND $GLOBALS['visiteur_session']['id_auteur'])
		sql_updateq("spip_transactions",
		  array("id_auteur"=>intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=".intval($id_transaction)
		);
	
	// recuperer l'email
	$mail = sql_getfetsel('email','spip_auteurs','id_auteur='.intval($row['id_auteur']));

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$montant = intval(round(100*$row['montant'],0));
	if (strlen($montant)<3)
		$montant = str_pad($montant,3,'0',STR_PAD_LEFT);

	//		Affectation des parametres obligatoires
	$parm = paybox_pbx_ids();
	$parm['PBX_OUTPUT']="C"; // recuperer uniquement les hidden
	$parm['PBX_LANGUE']="FRA";
	$parm['PBX_DEVISE']="978";
	$parm['PBX_TOTAL']=$montant;
	$parm['PBX_PORTEUR']=defined('_PBX_PORTEUR')?_PBX_PORTEUR:$mail;
	$parm['PBX_CMD']=intval($id_transaction);
	$parm['PBX_RETOUR'] = 'montant:M;id_transaction:R;auth:A;trans:S;abo:B;erreur:E;valid:D;sign:K';

	$parm['PBX_EFFECTUE']=generer_url_action('bank_response',"bankp=paybox",true,true);
	$parm['PBX_REFUSE']=generer_url_action('bank_cancel',"bankp=paybox",true,true);
	$parm['PBX_ANNULE']=generer_url_action('bank_cancel',"bankp=paybox",true,true);
 	$parm['PBX_REPONDRE_A']=generer_url_action('bank_autoresponse',"bankp=paybox",true,true);
	

	if ($abo
	  AND $id_abonnement = sql_getfetsel("id_abonnement","spip_abonnements_transactions","id_transaction=".intval($id_transaction))
	  AND $montant_echeance = sql_getfetsel('prix_echeance','spip_abonnements','id_abonnement='.intval($id_abonnement))
	  ){
		$montant_echeance = str_pad(intval(100*$montant_echeance), 10, "0", STR_PAD_LEFT);
		// infos de l'abonnement :
		// montant identique recurrent, frequence mensuelle, a date anniversaire, sans delai
		$parm['PBX_CMD'] .= 
		"IBS_2MONT$montant_echeance"
		. "IBS_NBPAIE00"
		. "IBS_FREQ01"
		. "IBS_QUAND00"
		//. "IBS_DELAIS000"
		;
	}
	//var_dump($parm);
	$cartes_possibles = array(
		'CB'=>'presta/paybox/logo/CB.gif',
		'VISA'=>'presta/paybox/logo/VISA.gif',
		'EUROCARD_MASTERCARD'=>'presta/paybox/logo/MASTERCARD.gif',
		'E_CARD'=>'presta/paybox/logo/E-CB.gif',
		'AMEX'=>'presta/paybox/logo/AMEX.gif',
		'AURORE'=>'presta/paybox/logo/AURORE.gif',
	);


	include_spip('inc/filtres_mini');
	$contexte = array('hidden'=>array(),'action'=>_PAYBOX_URL,'backurl'=>url_absolue(self()),'id_transaction'=>$id_transaction);
	$paybox_exec_request = charger_fonction("exec_request","presta/paybox");
	foreach($cartes as $carte){
		if (isset($cartes_possibles[$carte])){
			$parm['PBX_TYPEPAIEMENT'] = 'CARTE';
			$parm['PBX_TYPECARTE'] = $carte;
			$contexte['hidden'][$carte] = $paybox_exec_request($parm);
			$contexte['logo'][$carte] = $cartes_possibles[$carte];
		}
	}

	return $contexte;
}
?>
