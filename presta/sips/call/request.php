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

/**
 * Preparation de la requete par cartes
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param $config
 *   configuration du module
 * @return array|false
 */
function presta_sips_call_request_dist($id_transaction, $transaction_hash, $config){
	$mode = 'sips';
	if (!is_array($config) OR !isset($config['type']) OR !isset($config['presta'])){
		spip_log("call_request : config invalide " . var_export($config, true), $mode . _LOG_ERREUR);
		return false;
	}

	$mode = $config['presta'];

	if (!$row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction) . " AND transaction_hash=" . sql_quote($transaction_hash))){
		spip_log("call_request : transaction $id_transaction / $transaction_hash introuvable", $mode . _LOG_ERREUR);
		return false;
	}
	
	// On peut maintenant connaître la devise et ses infos
	$devise = $row['devise'];
	$devise_info = bank_devise_info($devise);
	if (!$devise_info) {
		spip_log("Transaction #$id_transaction : la devise $devise n’est pas connue", $mode . _LOG_ERREUR);
		return false;
	}

	if (!$row['id_auteur']
		AND isset($GLOBALS['visiteur_session']['id_auteur'])
		AND $GLOBALS['visiteur_session']['id_auteur']){
		sql_updateq("spip_transactions",
			array("id_auteur" => intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=" . intval($id_transaction)
		);
	}

	$mail = bank_porteur_email($row);

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$montant = intval(round((10**$devise_info['fraction']) * $row['montant'], 0));

	$merchant_id = $config['merchant_id'];
	$service = $config['service'];
	$certif = $config['certificat'];

	//		Affectation des parametres obligatoires
	$parm = array();
	$parm['merchant_id'] = $merchant_id;
	$parm['merchant_country'] = "fr";
	$parm['currency_code'] = (string)$devise_info['code_num'];
	$parm['amount'] = $montant;
	$parm['customer_id'] = intval($row['id_auteur']) ? $row['id_auteur'] : $row['auteur_id'];
	$parm['order_id'] = intval($id_transaction);
	$parm['transaction_id'] = bank_transaction_id($row);
	$parm['customer_email'] = substr($mail, 0, 128);

	$parm['normal_return_url'] = bank_url_api_retour($config, 'response');
	$parm['cancel_return_url'] = bank_url_api_retour($config, 'cancel');
	$parm['automatic_response_url'] = bank_url_api_retour($config, 'autoresponse');

	// Fix : les notifications serveurs en https depuis SIPSv1 semblent poser probleme et provoquent parfois des erreurs 500
	// on donne donc l'url en http, c'est une mauvaise solution mais on en a pas de meilleure.
	// Passer a un autre presta ou en sipsv2 si besoin
	if (strncmp($parm['automatic_response_url'], 'https://', 8)==0){
		$parm['automatic_response_url'] = "http://" . substr($parm['automatic_response_url'], 8);
	}

	// ajouter les logos de paiement si configures
	foreach (array('logo_id', 'logo_id2', 'advert') as $logo_key){
		if (isset($config[$logo_key])
			AND $file = $config[$logo_key]){
			$parm[$logo_key] = $file;
		}
	}

	//		Les valeurs suivantes ne sont utilisables qu'en pre-production
	//		Elles necessitent l'installation de vos fichiers sur le serveur de paiement
	//
	// 		$parm="$parm normal_return_logo=";
	// 		$parm="$parm cancel_return_logo=";
	// 		$parm="$parm submit_logo=";
	// 		$parm="$parm logo_id=";
	// 		$parm="$parm logo_id2=";
	// 		$parm="$parm advert=";
	// 		$parm="$parm background_id=";
	// 		$parm="$parm templatefile=";

	include_spip("presta/sips/inc/sips");
	$res = sips_request($service, $parm, $certif);

	// intercepter les vieux logo.gif de SIPS et leur donner une occasion d'etre remplace par les .svg commun a tous
	$logos = array();
	foreach ($res as $k => $s){
		if (is_string($s)
			and preg_match_all(",src=\"[^\"]*/presta/sips/logo/(\w+)\.gif\",Uims", $s, $matches, PREG_SET_ORDER)){

			foreach ($matches as $m){
				$what = $m[1];
				if (!isset($logos[$what])){
					$logos[$what] = bank_trouver_logo('sips', "{$what}.gif");
				}
				if ($logos[$what]){
					$res[$k] = str_replace($m[0], 'src="' . $logos[$what] . '"', $res[$k]);
				}
			}
		}
	}

	$res['service'] = $service;

	return $res;
}
