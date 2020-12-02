<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('presta/ogone/inc/ogone');

/*
<form  method="post"  action="https://secure.ogone.com/ncol/XXXX/orderstandard.asp"  id=form1   name=form1>
<!-­-­  general  parameters  -­-­>  
<input  type="hidden"  name="PSPID"  value="">  
<input  type="hidden"  name="operation"  value="SAL">
<input  type="hidden"  name="orderID"  value="">  
<input  type="hidden"  name="amount"  value="">  
<input  type="hidden"  name="currency"  value="">  
<input  type="hidden"  name="language"  value="">  
<input  type="hidden"  name="CN"  value="">  
<input  type="hidden"  name="EMAIL"  value="">  
<input  type="hidden"  name="ownerZIP"  value="">  
<input  type="hidden"  name="owneraddress"  value="">  
<input  type="hidden"  name="ownercty"  value="">  
<input  type="hidden"  name="ownertown"  value="">  
<input  type="hidden"  name="ownertelno"  value="">  
<!-­-­  check  before  the  payment:  see  chapter  5  -­-­>  
<input  type="hidden"  name="SHASign"  value="">  
<!-­-­  layout  information:  see  chapter  6  -­-­>  
<input  type="hidden"  name="TITLE"  value="">  
<input  type="hidden"  name="BGCOLOR"  value="">    
<input  type="hidden"  name="TXTCOLOR"  value="">    
<input  type="hidden"  name="TBLBGCOLOR"  value="">    
<input  type="hidden"  name="TBLTXTCOLOR"  value="">    
<input  type="hidden"  name="BUTTONBGCOLOR"  value="">    
<input  type="hidden"  name="BUTTONTXTCOLOR"  value="">    
<input  type="hidden"  name="LOGO"  value="">    
<input  type="hidden"  name="FONTTYPE"  value="">    
<!-­-­  post  payment  redirection:  see  chapter  7  -­-­>  
<input  type="hidden"  name="accepturl"  value=""> 
<input  type="hidden"  name="declineurl"  value="">  
<input  type="hidden"  name="exceptionurl"  value="">  
<input  type="hidden"  name="cancelurl"  value="">  
<input  type="submit"  value=""  id=submit2  name=submit2>  
</form>
*/

/**
 * Generer le contexte pour le formulaire de requete de paiement
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param $config
 *   configuration du module
 * @return array
 */
function presta_ogone_call_request_dist($id_transaction, $transaction_hash, $config){
	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction) . " AND transaction_hash=" . sql_quote($transaction_hash))){
		return array();
	}
	
	// On peut maintenant connaître la devise et ses infos
	$devise = $row['devise'];
	$devise_info = bank_devise_info($devise);
	
	if (!$row['id_auteur']
		AND isset($GLOBALS['visiteur_session']['id_auteur'])
		AND $GLOBALS['visiteur_session']['id_auteur']){
		sql_updateq("spip_transactions",
			array("id_auteur" => intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=" . intval($id_transaction)
		);
	}

	$cartes = array('VISA', 'MasterCard', 'American Express');
	if (isset($config['cartes']) AND is_array($config['cartes']) AND $config['cartes']){
		$cartes = $config['cartes'];
	}

	include_spip('inc/filtres');
	$contexte = array();

	$contexte['PSPID'] = $config['PSPID'];
	$contexte['orderID'] = $id_transaction . "/" . modulo($row['transaction_hash'], 999999);
	$contexte['operation'] = "SAL"; // c'est un paiement a l'acte immediat

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$contexte['currency'] = $devise_info['code'];
	$contexte['amount'] = intval(round((10**$devise_info['fraction']) * $row['montant'], 0));

	#if (strlen($montant)<3)
	#	$montant = str_pad($montant,3,'0',STR_PAD_LEFT);

	$contexte['language'] = ogone_language_code($GLOBALS['spip_lang']);

	// recuperer l'email
	$contexte['EMAIL'] = bank_porteur_email($row);
	$contexte['CN'] = "";

	$contexte['ownerZIP'] = "";
	$contexte['owneraddress'] = "";
	$contexte['ownercty'] = "";
	$contexte['ownertown'] = "";
	$contexte['ownertelno'] = "";

	// Urls de retour
	include_spip("inc/bank");
	$contexte['accepturl'] = bank_url_api_retour($config, 'response', "id=$id_transaction;$transaction_hash");
	$contexte['declineurl'] = bank_url_api_retour($config, 'cancel', "id=$id_transaction;$transaction_hash");
	$contexte['cancelurl'] = bank_url_api_retour($config, 'cancel', "id=$id_transaction;$transaction_hash");
	$contexte['exceptionurl'] = bank_url_api_retour($config, 'response', "id=$id_transaction;$transaction_hash");

	$hidden = "";
	foreach ($contexte as $k => $v){
		$hidden .= "<input type='hidden' name='$k' value='" . str_replace("'", "&#39;", $v) . "' />";
	}

	include_spip('inc/filtres_mini');
	$contexte = array(
		'hidden' => $hidden,
		'action' => ogone_url_serveur($config),
		'backurl' => url_absolue(self()),
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash
	);

	$cartes_possibles = ogone_available_cards($config);
	$contexte['cards'] = array();
	foreach ($cartes as $carte){
		if (isset($cartes_possibles[$carte])){
			$contexte['cards'][$carte] = $cartes_possibles[$carte];
		}
	}

	return $contexte;
}

