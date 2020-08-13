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

include_spip('inc/bank');
include_spip('presta/payzen/inc/payzen');

/*
 * @deprecated
 */
function systempay_url_serveur($config){return payzen_url_serveur($config);}
function systempay_key($config){return payzen_key($config);}
function systempay_available_cards($config){return payzen_available_cards($config);}
function systempay_form_hidden($config, $parms){return payzen_form_hidden($config, $parms);}
function systempay_signe_contexte($contexte, $key){return payzen_signe_contexte($contexte, $key);}
function systempay_verifie_signature($values, $key){return payzen_verifie_signature($values, $key);}
function systempay_recupere_reponse($config){return payzen_recupere_reponse($config);}
function systempay_traite_reponse_transaction($config, $response){return payzen_traite_reponse_transaction($config, $response);}
function systempay_response_code($code){return payzen_response_code($code);}
function systempay_auth_response_code($code){return payzen_auth_response_code($code);}