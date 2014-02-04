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
 * @return array
 */
function presta_ogone_call_request_dist($id_transaction, $transaction_hash){
	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash)))
		return array();

	if (!$row['id_auteur'] AND $GLOBALS['visiteur_session']['id_auteur'])
		sql_updateq("spip_transactions",
		  array("id_auteur"=>intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=".intval($id_transaction)
		);

	include_spip('inc/filtres');
	$contexte = array();

	$contexte['PSPID'] = ogone_pspid();
	$contexte['orderID'] = $id_transaction."/".modulo($row['transaction_hash'],999999);
	$contexte['operation'] = "SAL"; // c'est un paiement a l'acte immediat

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$contexte['currency'] = "EUR";
	$contexte['amount'] = intval(round(100*$row['montant'],0));

	#if (strlen($montant)<3)
	#	$montant = str_pad($montant,3,'0',STR_PAD_LEFT);

	$contexte['language'] = ogone_language_code($GLOBALS['spip_lang']);
	
	// recuperer l'email
	$contexte['EMAIL'] = sql_getfetsel('email','spip_auteurs','id_auteur='.intval($row['id_auteur']));
	$contexte['CN'] = "";

	$contexte['ownerZIP'] = "";
	$contexte['owneraddress'] = "";
	$contexte['ownercty'] = "";
	$contexte['ownertown'] = "";
	$contexte['ownertelno'] = "";

	// Urls de retour
	$contexte['accepturl'] = generer_url_action('bank_response',"bankp=ogone&id=$id_transaction;$transaction_hash",true,true);
	$contexte['declineurl'] = generer_url_action('bank_cancel',"bankp=ogone&id=$id_transaction;$transaction_hash",true,true);
	$contexte['cancelurl'] = generer_url_action('bank_cancel',"bankp=ogone&id=$id_transaction;$transaction_hash",true,true);
	$contexte['exceptionurl'] = generer_url_action('bank_response',"bankp=ogone&id=$id_transaction;$transaction_hash",true,true);

	$hidden = "";
	foreach($contexte as $k=>$v){
		$hidden .= "<input type='hidden' name='$k' value='".str_replace("'", "&#39;", $v)."' />";
	}

	include_spip('inc/filtres_mini');
	$contexte = array(
		'hidden'=>$hidden,
		'action'=>_OGONE_URL,
		'backurl'=>url_absolue(self()),
		'id_transaction'=>$id_transaction,
		'transaction_hash' => $transaction_hash
	);

	return $contexte;
}
?>
