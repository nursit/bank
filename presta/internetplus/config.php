<?php

// Les 3 lignes suivantes sont necessaires dans le htaccess

#RewriteRule ^pos_init$  spip.php?action=bank_response&bankp=internetplus [QSA,L]
#RewriteRule ^bundle-responder/responder$ spip.php?action=bank_autoresponse&bankp=internetplus [QSA,L]
#RewriteRule ^pos_bundle$ spip.php?action=bank_response&bankp=internetplus&abo=oui [QSA,L]



/**
 * constantes pour Internet+
 * plateforme de test
 * Achat a l'acte
 */


if (isset($GLOBALS['config_bank_paiement']['config_internetplus']['MERCHANT_ID'])
  AND $merchant_id = $GLOBALS['config_bank_paiement']['config_internetplus']['MERCHANT_ID']
  AND isset($GLOBALS['config_bank_paiement']['config_internetplus']['SECRET'])
  AND $secret = $GLOBALS['config_bank_paiement']['config_internetplus']['SECRET']
	AND !defined('_WHA_SECRET_'.$merchant_id)){

	define('_WHA_SECRET_'.$merchant_id, $secret);
}

if (isset($GLOBALS['config_bank_paiement']['config_abo_internetplus']['MERCHANT_ID'])
  AND $merchant_id = $GLOBALS['config_bank_paiement']['config_abo_internetplus']['MERCHANT_ID']
  AND isset($GLOBALS['config_bank_paiement']['config_abo_internetplus']['SECRET'])
  AND $secret = $GLOBALS['config_bank_paiement']['config_abo_internetplus']['SECRET']
  AND !defined('_WHA_SECRET_'.$merchant_id)){

	define('_WHA_SECRET_'.$merchant_id, $secret);
}
