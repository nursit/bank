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

/* Paypal Simple ----------------------------------------------------------- */
// parametres pour paiement standard paypal
if (!defined('_PAYPAL_BUSINESS_USERNAME'))
	define('_PAYPAL_BUSINESS_USERNAME', $GLOBALS['config_bank_paiement']['config_paypal']['BUSINESS_USERNAME']);


if (!defined('_PAYPAL_SANDBOX'))
	define('_PAYPAL_SANDBOX',
		(isset($GLOBALS['config_bank_paiement']['config_paypal']['mode_test'])
			AND $GLOBALS['config_bank_paiement']['config_paypal']['mode_test'])?true:false);

if (!defined('_PAYPAL_URL_SERVICES'))
	define('_PAYPAL_URL_SERVICES',
		_PAYPAL_SANDBOX?
			"https://www.sandbox.paypal.com:443/fr/cgi-bin/webscr"
			:
			"https://www.paypal.com:443/fr/cgi-bin/webscr");

?>