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

include_spip('presta/cmcic/inc/cmcic');

/*
<form action="<?php echo $oTpe->sUrlPaiement;?>" method="post" id="PaymentRequest">
<p>
	<input type="hidden" name="version"             id="version"        value="<?php echo $oTpe->sVersion;?>" />
	<input type="hidden" name="TPE"                 id="TPE"            value="<?php echo $oTpe->sNumero;?>" />
	<input type="hidden" name="date"                id="date"           value="<?php echo $sDate;?>" />
	<input type="hidden" name="montant"             id="montant"        value="<?php echo $sMontant . $sDevise;?>" />
	<input type="hidden" name="reference"           id="reference"      value="<?php echo $sReference;?>" />
	<input type="hidden" name="MAC"                 id="MAC"            value="<?php echo $sMAC;?>" />
	<input type="hidden" name="url_retour"          id="url_retour"     value="<?php echo $oTpe->sUrlKO;?>" />
	<input type="hidden" name="url_retour_ok"       id="url_retour_ok"  value="<?php echo $oTpe->sUrlOK;?>" />
	<input type="hidden" name="url_retour_err"      id="url_retour_err" value="<?php echo $oTpe->sUrlKO;?>" />
	<input type="hidden" name="lgue"                id="lgue"           value="<?php echo $oTpe->sLangue;?>" />
	<input type="hidden" name="societe"             id="societe"        value="<?php echo $oTpe->sCodeSociete;?>" />
	<input type="hidden" name="texte-libre"         id="texte-libre"    value="<?php echo HtmlEncode($sTexteLibre);?>" />
	<input type="hidden" name="mail"                id="mail"           value="<?php echo $sEmail;?>" />
	<!-- Uniquement pour le Paiement fractionné -->
	<input type="hidden" name="nbrech"              id="nbrech"         value="<?php echo $sNbrEch;?>" />
	<input type="hidden" name="dateech1"            id="dateech1"       value="<?php echo $sDateEcheance1;?>" />
	<input type="hidden" name="montantech1"         id="montantech1"    value="<?php echo $sMontantEcheance1;?>" />
	<input type="hidden" name="dateech2"            id="dateech2"       value="<?php echo $sDateEcheance2;?>" />
	<input type="hidden" name="montantech2"         id="montantech2"    value="<?php echo $sMontantEcheance2;?>" />
	<input type="hidden" name="dateech3"            id="dateech3"       value="<?php echo $sDateEcheance3;?>" />
	<input type="hidden" name="montantech3"         id="montantech3"    value="<?php echo $sMontantEcheance3;?>" />
	<input type="hidden" name="dateech4"            id="dateech4"       value="<?php echo $sDateEcheance4;?>" />
	<input type="hidden" name="montantech4"         id="montantech4"    value="<?php echo $sMontantEcheance4;?>" />
	<!-- -->
	<input type="submit" name="bouton"              id="bouton"         value="Connexion / Connection" />
</p>
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
function presta_cmcic_call_request_dist($id_transaction, $transaction_hash) {
	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash)))
		return array();

	include_spip('inc/filtres');
	$contexte = array();

	$oTpe  = new CMCIC_Tpe( strtoupper($GLOBALS['spip_lang']) );
	$oHmac = new CMCIC_Hmac($oTpe);   

	// Control String for support
	$CtlHmac = sprintf(_CMCIC_CTLHMAC,
		$oTpe->sVersion,
		$oTpe->sNumero,
		$oHmac->computeHmac(sprintf(_CMCIC_CTLHMACSTR, $oTpe->sVersion, $oTpe->sNumero)));

	// Currency : ISO 4217 compliant
	$devise = "EUR";
	// Amount : format  "xxxxx.yy" (no spaces)
	$montant = $row['montant'];
	$contexte['version']     = $oTpe->sVersion;
	$contexte['TPE']         = $oTpe->sNumero;
	// transaction date : format d/m/y:h:m:s
	$contexte['date']        = date("d/m/Y:H:i:s");
	$contexte['montant']     = $montant . $devise;
	// Reference: unique, alphaNum (A-Z a-z 0-9), 12 characters max
	$contexte['reference']   = substr($transaction_hash, 0, 12);
	$contexte['lgue']        = $oTpe->sLangue;
	$contexte['societe']     = $oTpe->sCodeSociete;

	// on transmet dans le texte libre les données id_transaction & hash
	// pour les avoir dans le retour URL CGI2 qui est une url à donner à la banque
	// et qui n'a pas connaissance autrement de notre id_transaction et hash :(
	// URL CGI2 à donner à la banque :
	// http(s)://votresite.org/?action=bank_autoresponse&bankp=cmcic
	$contenu = array(
		'id' => $id_transaction,
		'hash' => $transaction_hash,
		'lang' => $GLOBALS['spip_lang'], # pour le hit de la banque, pouvoir retrouver la langue utilisateur
		'contenu' => $row['contenu'], # déjà sérializé en théorie
	);
	// texte-libre doit etre protege car le formulaire est parfois reinjecte par Cmcic
	// dans une page de redirection pour les mobiles
	$contexte['texte-libre'] = urlencode(serialize($contenu));

	$contexte['mail']        = bank_email_porteur($row);
 

	// Data to certify
	$PHP1_FIELDS = sprintf(_CMCIC_CGI1_FIELDS,
		$contexte['TPE'],
		$contexte['date'],
		$montant, # montant
		$devise,  # devise
		$contexte['reference'], # reference de transaction 12c
		$contexte['texte-libre'],
		$oTpe->sVersion,
		$oTpe->sLangue,
		$oTpe->sCodeSociete, 
		$contexte['mail'],
		"", // $sNbrEch.
		"", // $sDateEcheance1,
		"", // $sMontantEcheance1,
		"", // $sDateEcheance2,
		"", // $sMontantEcheance2,
		"", // $sDateEcheance3,
		"", // $sMontantEcheance3,
		"", // $sDateEcheance4,
		"", // $sMontantEcheance4,
		"" // $sOptions
	);

	// MAC computation
	$contexte['MAC'] = $oHmac->computeHmac($PHP1_FIELDS);

	// Urls de retour.
	// La banque poste d'abord sur l'URL CGI2 (cf cmcic/config.php) qui doit traiter
	// le paiement positif et en attend une réponse (texte).
	// Puis, elle présente sur la banque au choix ces urls pour revenir sur le site
	// - retour OK si le paiement s'est bien déroulé
	$contexte['url_retour_ok']  = generer_url_action('bank_response',"bankp=cmcic&id=$id_transaction;$transaction_hash",true,true);
	// - retour err si le paiement a été refusé
	$contexte['url_retour_err'] = generer_url_action('bank_cancel',"bankp=cmcic&id=$id_transaction;$transaction_hash",true,true);
	// - retour (bouton Annuler) si le bonhomme décide d'abandonner le paiement
	$contexte['url_retour'] = generer_url_action('bank_response',"bankp=cmcic&id=$id_transaction;$transaction_hash",true,true);

	$hidden = "";
	foreach($contexte as $k=>$v){
		$hidden .= "<input type='hidden' name='$k' value='".str_replace("'", "&#39;", $v)."' />";
	}

	include_spip('inc/filtres_mini');
	$contexte = array(
		'hidden' => $hidden,
		'action' => _CMCIC_SERVEUR,
		'backurl' => url_absolue(self()),
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash);

	return $contexte;
}
?>
