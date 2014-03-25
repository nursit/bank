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


if (!defined('_WHA_NODE_URL'))
	define('_WHA_NODE_URL', defined('_INTERNETPLUS_SANDBOX')?'https://qualif-marchand.w-ha.com/app-authorization/node':'https://route.w-ha.com/app-authorization/node');

if (!defined('_WHA_MERCHANT_ID'))
	define('_WHA_MERCHANT_ID', $GLOBALS['config_bank_paiement']['config_internetplus']['MERCHANT_ID']);

if (!defined('_WHA_KEY_ID'))
	define('_WHA_KEY_ID_', $GLOBALS['config_bank_paiement']['config_internetplus']['KEY_ID']);

if (!defined('_WHA_SECRET_'._WHA_MERCHANT_ID))
	define('_WHA_SECRET_'._WHA_MERCHANT_ID, $GLOBALS['config_bank_paiement']['config_internetplus']['SECRET']);



if (!defined('_WHA_NODE_ABO_URL'))
	define('_WHA_NODE_ABO_URL', defined('_INTERNETPLUS_SANDBOX')?'https://qualif-marchand.w-ha.com/app-bundlepurchase/node':'https://route.w-ha.com/app-bundlepurchase/node');

if (!defined('_WHA_ABO_MERCHANT_ID'))
	define('_WHA_ABO_MERCHANT_ID', $GLOBALS['config_bank_paiement']['config_abo_internetplus']['MERCHANT_ID']);

if (!defined('_WHA_ABO_KEY_ID'))
	define('_WHA_ABO_KEY_ID', $GLOBALS['config_bank_paiement']['config_abo_internetplus']['KEY_ID']);

if (!defined('_WHA_SECRET_'._WHA_ABO_MERCHANT_ID))
	define('_WHA_SECRET_'._WHA_ABO_MERCHANT_ID, $GLOBALS['config_bank_paiement']['config_abo_internetplus']['SECRET']);
