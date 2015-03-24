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

/**
 * Preparation de la requete par cartes
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param $config
 *   configuration du module
 * @return array
 */
function presta_paybox_call_request_dist($id_transaction, $transaction_hash, $config){

	$mode = 'paybox';
	if (!is_array($config) OR !isset($config['type']) OR !isset($config['config'])){
		spip_log("call_request : config invalide ".var_export($config,true),'paybox'._LOG_ERREUR);
		$mode = $config['config'];
	}

	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash))){
		spip_log("call_request : transaction $id_transaction / $transaction_hash introuvable",$mode._LOG_ERREUR);
		return "";
	}

	$cartes = array('CB','VISA','EUROCARD_MASTERCARD','E_CARD');
	if (isset($config['cartes']) AND is_array($config['cartes']) AND $config['cartes'])
		$cartes = $config['cartes'];

	if (!$row['id_auteur'] AND $GLOBALS['visiteur_session']['id_auteur'])
		sql_updateq("spip_transactions",
		  array("id_auteur"=>intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=".intval($id_transaction)
		);

	$mail = bank_email_porteur($row);

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$montant = intval(round(100*$row['montant'],0));
	if (strlen($montant)<3)
		$montant = str_pad($montant,3,'0',STR_PAD_LEFT);

	//		Affectation des parametres obligatoires
	$parm = $config;
	$parm['PBX_OUTPUT']="C"; // recuperer uniquement les hidden
	$parm['PBX_LANGUE']="FRA";
	$parm['PBX_DEVISE']="978";
	$parm['PBX_TOTAL']=$montant;
	$parm['PBX_PORTEUR']=defined('_PBX_PORTEUR')?_PBX_PORTEUR:$mail;
	$parm['PBX_CMD']=intval($id_transaction);

	// si le porteur est generique, on ajoute l'email au numero de commande
	// pour la tracabilite dans l'admin paybox
	if (defined('_PBX_PORTEUR'))
		$parm['PBX_CMD'] .= "/".$mail;

	// temps de validite de la page de paiement paybox (par defaut 900s)
	if (defined('_PBX_DISPLAY'))
		$parm['PBX_DISPLAY'] = _PBX_DISPLAY;

	$parm['PBX_EFFECTUE'] = bank_url_api_retour($config,"response");
	$parm['PBX_REFUSE'] = bank_url_api_retour($config,"cancel");
	$parm['PBX_ANNULE'] = bank_url_api_retour($config,"cancel");
 	$parm['PBX_REPONDRE_A'] = bank_url_api_retour($config,"autoresponse");

	$parm['PBX_RETOUR'] = 'montant:M;id_transaction:R;auth:A;trans:S;abo:B;erreur:E;valid:D;';

	if ($config['type']=='abo'){
		// on decrit l'echeance, en indiquant qu'on peut la gerer manuellement grace a PayBoxDirectPlus
		if (
			$decrire_echeance = charger_fonction("decrire_echeance","abos",true)
		  AND $echeance = $decrire_echeance($id_transaction, false)){
			if ($echeance['montant']>0){
				$montant_echeance = str_pad(intval(round(100*$echeance['montant'])), 10, "0", STR_PAD_LEFT);

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
			else
				$parm['PBX_RETOUR'] .= 'ppps:U;';
		}
	}

	// fermer le retour avec la signature
	$parm['PBX_RETOUR'] .= 'sign:K';



	//var_dump($parm);
	$cartes_possibles = array(
		'CB'=>'presta/paybox/logo/CB.gif',
		'VISA'=>'presta/paybox/logo/VISA.gif',
		'EUROCARD_MASTERCARD'=>'presta/paybox/logo/MASTERCARD.gif',
	);
	if ($config['type']!=='abo'){
		$cartes_possibles['E_CARD']='presta/paybox/logo/E-CB.gif';
		$cartes_possibles['AMEX']='presta/paybox/logo/AMEX.gif';
		$cartes_possibles['AURORE']='presta/paybox/logo/AURORE.gif';
	}

	include_spip('inc/filtres_mini');

	$contexte = array(
		'hidden'=>array(),
		'action'=>paybox_url_paiment($config),
		'backurl'=>url_absolue(self()),
		'id_transaction'=>$id_transaction
	);

	foreach($cartes as $carte){
		if (isset($cartes_possibles[$carte])){
			$parm['PBX_TYPEPAIEMENT'] = 'CARTE';
			$parm['PBX_TYPECARTE'] = $carte;
			$contexte['hidden'][$carte] = paybox_form_hidden($parm);
			$contexte['logo'][$carte] = $cartes_possibles[$carte];
		}
	}

	return $contexte;
}
