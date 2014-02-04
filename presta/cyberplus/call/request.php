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

include_spip('presta/cyberplus/inc/cyberplus');

/*

;   #####################################################
;   #                                                   #
;   # Ficher de configuration du formulaire de paiement #
;   #                                                   #
;   #        Payment form configuration file            #
;   #                                                   #
;   #####################################################


;-------------------------------
;*******************************
;* MODE DEBUGGAGE / DEBUG MODE *
;*******************************
;-------------------------------

; ------francais------
; ON: Permet d'afficher les champs qui seront envoyes a la plateforme.
; OFF: Redirection automatique vers la page de paiement.

; ------english------
; ON: Allows to display the fields which will be sent to the shop.
; OFF: Automatic redirection to the payment page.

debug = ON


;------------------------------------------
;******************************************
;* ACCES A LA PLATEFORME / GATEWAY ACCESS *
;******************************************
;------------------------------------------

;--------------
; vads_site_id 
;--------------

; ------francais------
; Identifiant Boutique à récupérer dans le Back office de la solution de paiement.

; ------english------
; Shop ID to take out from the Systempay back office.

vads_site_id = 11111111


;------
; keys
;------

; ------francais------
; Certificat à récupérer dans le Back office de la solution de paiement. 
; Attention ce certificat est différent en fonction de vads_ctx_mode, TEST ou PRODUCTION. 
; Le certificat n'est pas envoyé à la plateforme de paiement mais intervient dans le calcul de la signature.

; ------english------
; Certificate to take out from the Systempay back office.
; Warning, this certificate is different depending on the vads_ctx_mode setting, TEST or PRODUCTION.
; The certificate is not sent to the gateway but is used to create the signature.

TEST_key = 2222222222222222
PROD_key = 3333333333333333


;---------------
; vads_ctx_mode 
;---------------

; ------francais------
; Mode de fonctionnement. Valeur = TEST ou PRODUCTION

; ------english------
; Mode. TEST or PRODUCTION

vads_ctx_mode = TEST


;--------------
; vads_version 
;--------------

; ------francais------
; Ce paramètre est obligatoire et doit être valorisé à V2.

; ------english------
; This setting is compulsory and must be set to V2.

vads_version = V2


;---------------
; vads_language 
;---------------

; ------francais------
; Langue dans laquelle s'affiche la page de paiement.
; fr pour Français, en pour Anglais.

; ------english------
; Language of the payment page.
; fr for French, en for English.

vads_language = fr


;-------------------------------------------------------
;*******************************************************
;* PARAMETRES DE LA TRANSACTION / TRANSACTION SETTINGS *
;*******************************************************
;-------------------------------------------------------


;-----------
; signature 
;-----------

; ------francais------
; La signature est un paramètre obligatoire. Elle est calculée par la fonction Get_signature du fichier 
; function.php inclu dans ce pack.

; ------english------
; The signature is a mandatory setting. It is created by the Get_signature function of the function.php 
; file included in this package.

;signature =


;-----------------
; vads_trans_date
;-----------------

; ------francais------
; Ce champ est obligatoire, il correspond à la date de la transaction exprimée sous la forme AAAAMMJJHHMMSS 
; sur le fuseau UTC=0. Cette valeur sera calculée par le fichier function.php.

; ------english------
; This field is compulsory, it matches the transaction date with the following pattern YYYYMMDDHHMMSS on 
; the UTC=0 time zone. This value is calculated by the function.php file.

;vads_trans_date =


;---------------
; vads_trans_id -
;--------------

; ------francais------
; Ce champ est obligatoire, il correspond à l'identifiant de la transaction. Cet identifiant doit être:
; - unique sur une même journée.
; - sa longueur est obligatoirement de 6 caractères.
; - Sa valeur est doit être comprise entre 000000 et 899999.
; DANS CET EXEMPLE LE CALCUL DE CE CHAMP EST FAIT PAR LE FICHIER "function.php" et s'appuie sur un compteur. 
; VOUS POUVEZ CALCULER CE CHAMP A VOTRE CONVENANCE EN RESPECTANT LES REGLES DU CHAMP TRANS_ID.

; ------english------
; This field is mandatory, it matches the transaction ID. This ID must be:
; - unique on the day.
; - its length must be 6 characters.
; - its value must be between 000000 and 899999.
; IN THIS EXAMPLE, THSI FIELD IS CALCULATED BY "function.php" AND USES A COUNTER;
; YOU CAN CREATE THIS FIELD AS YOU WANT AS LONG AS YOUR RESPECT ITS RULES.

;vads_trans_id =


;---------------
; vads_currency 
;---------------

; ------francais------
; Code devise. 978 pour EURO.

; ------english------
; Currency code. 978 for EURO.

vads_currency = 978


;------------------
; vads_page_action 
;------------------

; ------francais------
; Ce paramètre est obligatoire et doit être valorisé à PAYMENT.

; ------english------
; This field is compulsory and must be set to PAYMENT.

vads_page_action = PAYMENT


;------------------
; vads_action_mode 
;------------------

; ------francais------
; Ce paramètre est valorisé à INTERACTIVE si la saisie de carte est réalisée sur la plateforme de paiement. 

; ------english------
; This setting is set to INTERACTIVE if the card details are entered on the payment gateway.

vads_action_mode = INTERACTIVE


;---------------------
; vads_payment_config 
;---------------------

; ------francais------
; Ce paramètre est valorisé à SINGLE pour un paiement simple.

; ------english------
; This parameter is set to SINGLE for unique payment.

vads_payment_config = SINGLE


;--------------------
; vads_capture_delay 
;--------------------

; ------francais------
; Ce Paramètre  facultatif indique le délai en nombre de jours avant remise en banque. Si ce 
; paramètre nest pas transmis, alors la valeur par défaut définie dans le back office marchand 
; sera utilisée. Cette dernière est paramétrable dans loutil de gestion de caisse  Systempay  par 
; toutes les personnes dûment habilitées.

; ------english------
; This setting is optional and matches with the capture delay. If this setting is not set, its value
; will be the one defined on the merchant back office. This value can be configured on the Systempay back 
; office by duly authorized persons.

;vads_capture_delay = 0


;----------------------
; vads_validation_mode 
;----------------------

; ------francais------
; Paramètre  facultatif précisant le mode de validation de la transaction. 
; 1: Validation manuelle par le commerçant
; 0: Validation automatique par la plateforme
; Si ce paramètre nest pas transmis, alors la valeur par défaut définie 
; dans le back-office marchand sera utilisée.

; ------english------
; Optional setting matching the transaction validation mode
; 1: Manual validation by the merchant
; 0: Automatic validation by the gateway
; If this setting is not set, its value will be the one defined on the merchant back office. 

;vads_validation_mode = 0

;---------------------------------------------
;*********************************************
;* RETOUR A LA BOUTIQUE / RETURN TO THE SHOP *
;*********************************************
;---------------------------------------------

;-----------------
; vads_url_return 
;-----------------

; ------francais------
; Url de retour à la boutique. Lorsque le client clique sur "retourner à la boutique"
; cette url permet de faire un traitement affichage en indiquant l'état du paiement. 
; Il est fortement conseillé de ne pas faire de traitement en base de données 
; (mise à jour commande, enregistrement commande) suite à l'analyse du résultat du paiement.
; C'est l'appel de l'url serveur qui doit vous permettre de mettre à jour la base de données.

; ------english------
; Shop return URL. When the customer clicks on "return to the shop" this URL permits to treat 
; the data in order to display the payment details. It is strongly recommended NOT to treat 
; the data in the database (order update, order record) after the payment analysis. 
; The server URL must allow you to update the database.

vads_url_return = http://localhost/payment_form_systempay_1.0c/return_payment.php


;------------------
; vads_return_mode 
;------------------

; ------francais------
; Ce paramètre définit dans quel mode seront renvoyés les paramètres lors du retour à la boutique 
; (3 valeurs possibles GET / POST / NONE). Si ce champ n'est pas posté alors la plateforme ne renvoie 
; aucun paramètre lors du retour à la boutique par l'internaute.

; ------english------
; This setting defines the return mode by which the settings will be sent back to the shop
; (3 possible values GET / POST / NONE). If this field is not filled the gateway does not send back 
; any data to the shop when the customer returns to the shop.

vads_return_mode = GET


;-------------------------------
; vads_redirect_success_timeout 
;-------------------------------

; ------francais------
; Ce paramètre définit la durée avant un retour automatique à la boutique pour un paiement accepté 
; (valeur exprimée en seconde).

; ------english------
; This setting defines the duration before an automatic return to the shop if the payment is accepted
; (unit: seconds).
vads_redirect_success_timeout = 1


;-------------------------------
; vads_redirect_success_message 
;-------------------------------

; ------francais------
; Ce paramètre définit un message sur la page de paiement avant le retour automatique à la boutique 
; dans le cas d'un paiement accepté.

; ------english------
; This setting defines a message displayed on the payment gateway before an automatic return to 
; the shop if the payment is accepted.

vads_redirect_success_message = Redirection vers la boutique dans quelques instants


;-----------------------------
; vads_redirect_error_timeout 
;-----------------------------

; ------francais------
; Ce paramètre définit la durée avant un retour automatique à la boutique pour un paiement échoué 
; (valeur exprimée en seconde).

; ------english------
; This setting defines the duration before an automatic return to the shop if the payment failed
; (unit: seconds).

vads_redirect_error_timeout = 1


;-----------------------------
; vads_redirect_error_message 
;-----------------------------

; ------francais------
; Ce paramètre définit un message sur la page de paiement avant le retour automatique à la boutique 
; dans le cas d'un paiement échoué.

; ------english------
; This setting defines a message displayed on the payment gateway before an automatic return to 
; the shop if the payment failed.

vads_redirect_error_message = Redirection vers la boutique dans quelques instants


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
function presta_cyberplus_call_request_dist($id_transaction, $transaction_hash){
	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash)))
		return array();

	if (!$row['id_auteur'] AND $GLOBALS['visiteur_session']['id_auteur'])
		sql_updateq("spip_transactions",
		  array("id_auteur"=>intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=".intval($id_transaction)
		);

	include_spip('inc/filtres');

	$contexte = array();

	$contexte['vads_site_id'] = cyberplus_site_id();
	$contexte['vads_ctx_mode'] = _CYBERPLUS_MODE;
	$contexte['vads_version'] = _CYBERPLUS_VERSION;

	$contexte['vads_trans_id'] = str_pad(modulo($row['id_transaction'],899999),6,"0",STR_PAD_RIGHT);
	$contexte['vads_order_id'] = $row['id_transaction'];
	$contexte['vads_trans_date'] = gmdate ("YmdHis", time());

	$contexte['vads_page_action'] = "PAYMENT";
	$contexte['vads_action_mode'] = "INTERACTIVE";
	$contexte['vads_payment_config'] = "SINGLE";
	//$contexte['vads_capture_delay'] = 0;
	//$contexte['vads_validation_mode'] = 0;

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$contexte['vads_currency'] = 978;
	$contexte['vads_amount'] = intval(round(100*$row['montant'],0));

	$contexte['vads_language'] = $GLOBALS['spip_lang'];

	// recuperer l'email
	if (intval($row['id_auteur']))
		$contexte['vads_cust_email'] = sql_getfetsel('email','spip_auteurs','id_auteur='.intval($row['id_auteur']));

	// Urls de retour

	$contexte['vads_return_mode'] = "GET";//"POST";
	$contexte['vads_url_return'] = generer_url_action('bank_response',"bankp=cyberplus",true,true);

	#$contexte['vads_redirect_success_timeout'] = 1;
	#$contexte['vads_redirect_success_message'] = "OK";
	#$contexte['vads_redirect_error_timeout'] = 1;
	#$contexte['vads_redirect_error_message'] = "Echec";

	$contexte['signature'] = cybperplus_signe_contexte($contexte,_CYBERPLUS_CLE);

	$hidden = "";
	foreach($contexte as $k=>$v){
		$hidden .= "<input type='hidden' name='$k' value='".str_replace("'", "&#39;", $v)."' />";
	}

	include_spip('inc/filtres_mini');
	$contexte = array(
		'hidden'=>$hidden,
		'action'=>_CYBERPLUS_SERVEUR,
		'backurl'=>url_absolue(self()),
		'id_transaction'=>$id_transaction,
		'transaction_hash' => $transaction_hash
	);

	return $contexte;
}
?>
