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

include_spip('presta/cmcic/inc/cmcic');

/**
 * Determiner la strategie 3DS2 : non/souhaitable/requis via config
 * mais on peut faire quelque chose de plus fin en fonction de la transaction via une fonction perso presta_cmcic_3ds_policy()
 *
 * @param $id_transaction
 * @param $config
 * @return mixed|string
 */
function presta_cmcic_3ds_policy_dist($id_transaction, $config) {
	$default = 'no_preference';
	$allowed = [
		'no_preference',
		'challenge_preferred',
		'challenge_mandated',
		'no_challenge_requested',
		'no_challenge_requested_strong_authentication',
		'no_challenge_requested_trusted_third_party',
		'no_challenge_requested_risk_analysis'
	];
	if (function_exists($f = 'presta_cmcic_3ds_policy')) {
		$policy = $f($id_transaction, $config);
	}
	else {
		$policy = (empty($config['3DS2_POLICY']) ? $default  : $config['3DS2_POLICY']);
	}
	if (!in_array($policy, $allowed)) {
		$policy = $default;
	}
	return $policy;
}

/*
<form action="<?php echo $oTpe->sUrlPaiement;?>" method="post" id="PaymentRequest">
<p>
	<input type="hidden" name="version"             id="version"           value="<?php echo $oEpt->sVersion;?>" />
	<input type="hidden" name="TPE"                 id="TPE"               value="<?php echo $oEpt->sNumero;?>" />
	<input type="hidden" name="date"                id="date"              value="<?php echo $sDate;?>" />
	<input type="hidden" name="contexte_commande"   id="contexte_commande" value="<?php echo $sContexteCommande;?>" />
	<input type="hidden" name="montant"             id="montant"           value="<?php echo $sMontant . $sDevise;?>" />
	<input type="hidden" name="reference"           id="reference"         value="<?php echo $sReference;?>" />
	<input type="hidden" name="MAC"                 id="MAC"               value="<?php echo $sMAC;?>" />
	<input type="hidden" name="url_retour_ok"       id="url_retour_ok"     value="<?php echo $oEpt->sUrlOK;?>" />
	<input type="hidden" name="url_retour_err"      id="url_retour_err"    value="<?php echo $oEpt->sUrlKO;?>" />
	<input type="hidden" name="lgue"                id="lgue"              value="<?php echo $oEpt->sLangue;?>" />
	<input type="hidden" name="societe"             id="societe"           value="<?php echo $oEpt->sCodeSociete;?>" />
	<input type="hidden" name="texte-libre"         id="texte-libre"       value="<?php echo HtmlEncode($sTexteLibre);?>" />
	<input type="hidden" name="mail"                id="mail"              value="<?php echo $sEmail;?>" />
	<!-------------------------------------------------------------------------------------------------------------------------------------------------------------
      SECTION PAIEMENT FRACTIONNE - Section spécifique au paiement fractionné

	  INSTALLMENT PAYMENT SECTION - Section specific to the installment payment
	-------------------------------------------------------------------------------------------------------------------------------------------------------------->
	<input type="hidden" name="nbrech"              id="nbrech"         value="<?php echo $sNbrEch;?>" />
	<input type="hidden" name="dateech1"            id="dateech1"       value="<?php echo $sDateEcheance1;?>" />
	<input type="hidden" name="montantech1"         id="montantech1"    value="<?php echo $sMontantEcheance1;?>" />
	<input type="hidden" name="dateech2"            id="dateech2"       value="<?php echo $sDateEcheance2;?>" />
	<input type="hidden" name="montantech2"         id="montantech2"    value="<?php echo $sMontantEcheance2;?>" />
	<input type="hidden" name="dateech3"            id="dateech3"       value="<?php echo $sDateEcheance3;?>" />
	<input type="hidden" name="montantech3"         id="montantech3"    value="<?php echo $sMontantEcheance3;?>" />
	<input type="hidden" name="dateech4"            id="dateech4"       value="<?php echo $sDateEcheance4;?>" />
	<input type="hidden" name="montantech4"         id="montantech4"    value="<?php echo $sMontantEcheance4;?>" />
	<!-------------------------------------------------------------------------------------------------------------------------------------------------------------
      FIN SECTION PAIEMENT FRACTIONNE

	  END INSTALLMENT PAYMENT SECTION
	-------------------------------------------------------------------------------------------------------------------------------------------------------------->
	<input type="submit" name="bouton"              id="bouton"         value="Connexion / Connection" /></p>
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
function presta_cmcic_call_request_dist($id_transaction, $transaction_hash, $config){
	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction) . " AND transaction_hash=" . sql_quote($transaction_hash))){
		return array();
	}

	include_spip('inc/filtres');
	$contexte = array();

	$oTpe = new MoneticoPaiement_Ept($config, strtoupper($GLOBALS['spip_lang']));
	if (!$oTpe->isOK){
		return false;
	}

	$oHmac = new MoneticoPaiement_Hmac($oTpe);

	// Control String for support
	$CtlHmac = sprintf(_MONETICOPAIEMENT_CTLHMAC,
		_MONETICOPAIEMENT_VERSION,
		$oTpe->sVersion,
		$oTpe->sNumero,
		$oHmac->computeHmac(sprintf(_MONETICOPAIEMENT_CTLHMACSTR, $oTpe->sVersion, $oTpe->sNumero)));


	// Currency : ISO 4217 compliant
	$devise = "EUR";
	// Amount : format  "xxxxx.yy" (no spaces)
	$montant = $row['montant'];
	$contexte['version'] = $oTpe->sVersion;
	$contexte['TPE'] = $oTpe->sNumero;
	// transaction date : format d/m/y:h:m:s
	$contexte['date'] = date("d/m/Y:H:i:s");
	$contexte['montant'] = $montant . $devise;
	// Reference: unique, alphaNum (A-Z a-z 0-9), 12 characters max
	$contexte['reference'] = "PAY" . str_pad($id_transaction, 9, "0", STR_PAD_LEFT); //ubstr($transaction_hash, 0, 12);
	$contexte['lgue'] = $oTpe->sLangue;
	$contexte['societe'] = $oTpe->sCodeSociete;

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

	$contexte['mail'] = bank_porteur_email($row);

	$contexte['ThreeDSecureChallenge'] = presta_cmcic_3ds_policy_dist($id_transaction, $config);

	// Urls de retour.
	// La banque poste d'abord sur l'URL CGI2 (cf cmcic/config.php) qui doit traiter
	// le paiement positif et en attend une réponse (texte).
	// Puis, elle présente sur la banque au choix ces urls pour revenir sur le site
	// - retour OK si le paiement s'est bien déroulé
	$contexte['url_retour_ok'] = bank_url_api_retour($config, "response", "id=$id_transaction;$transaction_hash");
	// - retour err si le paiement a été refusé
	$contexte['url_retour_err'] = bank_url_api_retour($config, "cancel", "id=$id_transaction;$transaction_hash");

	// contexte_commande DSP2
	// Contextual information related to the order : JSON, UTF-8, base64 encoded
	// cart details, shipping and delivery addresses, technical context
	$contexte_commande = [
		// obligatoire
		'billing' => [
			'addressLine1' => '',
			// 'stateOrProvince' => '', // obligatoire pour US et CA
			'city' => '',
			'postalCode' => '',
			'country' => '',
		],
		// obligatoire mais tous les champs sont optionels (ou obligatoire 'si applicables' -> on laisse tout vide)
		// il ne faut pas envoyer un tableau vide cela declenche une erreur chez monetico, donc on ne renseigne pas
		// 'shipping' => [],

		// optionnel, on ne renseigne pas
		// 'shoppingCart' => [],
		// optionnel, service minimum
		'client' => [
			'email' => $contexte['mail'],
		],
	];

	$billing = bank_porteur_infos_facturation($row);
	if ($billing['prenom']){
		$contexte_commande['billing']['firstName'] = $billing['prenom'];
	}
	if ($billing['nom']){
		$contexte_commande['billing']['lastName'] = $billing['nom'];
	}
	if ($billing['adresse']){
		$lignes = explode("\n", $billing['adresse']);
		$contexte_commande['billing']['addressLine1'] = array_shift($lignes);
		if (count($lignes)){
			$contexte_commande['billing']['addressLine2'] = array_shift($lignes);
		}
		if (count($lignes)){
			$contexte_commande['billing']['addressLine3'] = implode(' ', $lignes);
		}
	}
	if ($billing['ville']){
		$contexte_commande['billing']['city'] = $billing['ville'];
	}
	if ($billing['code_postal']){
		$contexte_commande['billing']['postalCode'] = $billing['code_postal'];
	}
	if ($billing['etat']){
		$contexte_commande['billing']['stateOrProvince'] = $billing['etat'];
	}
	if ($billing['pays']){
		$contexte_commande['billing']['country'] = $billing['pays'];
	}

	$contexte_commande = json_encode($contexte_commande);
	if ($GLOBALS['meta']['charset']!=='utf-8'){
		$contexte_commande = utf8_encode($contexte_commande);
	}
	$contexte_commande_base64 = base64_encode($contexte_commande);
	$contexte['contexte_commande'] = $contexte_commande_base64;

	// recuperer les champs tries en chaine a signer, et complete le contexte au passage si besoin
	$phase1go_fields = cmcic_concat_fields($contexte);
	// MAC computation
	$contexte['MAC'] = $oHmac->computeHmac($phase1go_fields);

	$hidden = "";
	foreach ($contexte as $k => $v){
		$hidden .= "<input type='hidden' name='$k' value='" . str_replace("'", "&#39;", $v) . "' />";
	}

	include_spip('inc/filtres_mini');
	$contexte = array(
		'hidden' => $hidden,
		'action' => cmcic_url_serveur($config),
		'backurl' => url_absolue(self()),
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash);

	return $contexte;
}

