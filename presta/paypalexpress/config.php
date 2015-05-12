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


/**
 * Attention, ce mode de paiement fonction un peu differemnt des autres :
 * - on envoie sur Paypal Express Checkout pour avoir un jeton de paiement et que l'utilisateur se loge sur son compte paypal
 * - puis on revient ici avec le jeton, et on renvoie sur la page initiale de paiement avec
 *   &confirm=oui
 *   &checkout= url finale pour valide le paiement
 *   $_SESSION['order_resume'] le resume des infos utilisees pour le paiement
 *
 * On revient donc une deuxieme fois sur la page de paiement, et le modele payer_acte affiche le form de confirmation
 * au lieu de la liste des moyens de paiement
 *
 *
 */

session_start();

/****************************************************
Constantes par defaut pour le paiement PAYPAL, a configurer dans mes_options
This is the configuration file for the samples.This file
defines the parameters needed to make an API call.

PayPal includes the following API Signature for making API
calls to the PayPal sandbox:

API Username 	sdk-three_api1.sdk.com
API Password 	QFZCWN5HZM8VBG7Q
API Signature 	A-IzJhZZjhg29XQ2qnhapuwxIDzyAZQ92FRP5dqBzVesOkzbdUONzmOU

Called by CallerService.php.
****************************************************/


/**
USE_PROXY: Set this variable to TRUE to route all the API requests through proxy.
like define('USE_PROXY',TRUE);
*/
define('_PAYPAL_API_USE_PROXY',FALSE);
/**
PROXY_HOST: Set the host name or the IP address of proxy server.
PROXY_PORT: Set proxy port.

PROXY_HOST and PROXY_PORT will be read only if USE_PROXY is set to TRUE
*/
define('_PAYPAL_API_PROXY_HOST', '127.0.0.1');
define('_PAYPAL_API_PROXY_PORT', '808');


/**
# Version: this is the API version in the request.
# It is a mandatory parameter for each API request.
# The only supported value at this time is 2.3
*/

define('_PAYPAL_API_VERSION', '3.0');

/**
 * Action signee pour lancer la demande de paiement
 * @param null|string $arg
 */
function action_paypalexpress_order_dist($arg=null){
	if (is_null($arg)){
		$securiser_action = charger_fonction('securiser_action','inc');
		$arg = $securiser_action();
	}

	// id_transaction-mode (qui peut etre sous la forme paypal-XXXX avec son id de presta)
	$arg = explode("-",$arg);
	$id_transaction = array_shift($arg);
	$mode = implode("-",$arg);
	include_spip('inc/bank');
	$config = bank_config($mode);

/* An express checkout transaction starts with a token, that
   identifies to PayPal your transaction
   In this example, when the script sees a token, the script
   knows that the buyer has already authorized payment through
   paypal.  If no token was found, the action is to send the buyer
   to PayPal to first authorize payment
   */

	if(!$token = _request('token')) {

		include_spip("presta/paypalexpress/inc/paypalexpress");
		$redirect = bank_paypalexpress_order_init($config, $id_transaction,_request('url_confirm'));

		if (!$redirect)
			$redirect = url_de_base();
	}

	$GLOBALS['redirect'] = $redirect;
}


function action_paypalexpress_checkoutpayment_dist($arg=null){
	if (is_null($arg)){
		$securiser_action = charger_fonction('securiser_action','inc');
		$arg = $securiser_action();
	}

	$arg = explode("-",$arg);
	$payerid = array_shift($arg);
	$mode = implode("-",$arg);

	include_spip("inc/bank");
	$config = bank_config($mode);

	include_spip("presta/paypalexpress/inc/paypalexpress");
	$res = bank_paypalexpress_checkoutpayment($payerid,$config);

	list($id_transaction, $success) = $res;

	include_spip("action/bank_response");
	redirige_apres_retour_transaction("paypalexpress","acte",$success,$id_transaction);
}

