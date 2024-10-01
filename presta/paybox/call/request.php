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


include_spip('presta/paybox/inc/paybox');

/**
 * Preparation de la requete par cartes
 * il faut avoir un id_transaction et un transaction_hash coherents
 * pour se premunir d'une tentative d'appel exterieur
 *
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param $config
 *   configuration du module
 * @param string $type
 *   type de paiement : acte ou abo
 * @return array|false
 */
function presta_paybox_call_request_dist($id_transaction, $transaction_hash, $config, $type = "acte"){

	$mode = 'paybox';
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

	$cartes = array('CB', 'VISA', 'EUROCARD_MASTERCARD', 'E_CARD');
	if (isset($config['cartes']) AND is_array($config['cartes']) AND $config['cartes']){
		$cartes = $config['cartes'];
	}

	if (!$row['id_auteur']
		AND isset($GLOBALS['visiteur_session']['id_auteur'])
		AND $GLOBALS['visiteur_session']['id_auteur']){
		sql_updateq("spip_transactions",
			array("id_auteur" => intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=" . intval($id_transaction)
		);
	}

	$billing = bank_porteur_infos_facturation($row);
	$mail = $billing['email'];
	
	// passage en centimes et formattage
	$montant = bank_formatter_montant_selon_fraction($row['montant'], $devise_info['fraction'], 3);

	// Affectation des parametres obligatoires
	// seuls les PBX_ sont envoyees dans le formulaire
	$parm = $config;

	// cas de PBX_RANG : paybox fournit 001 mais il faut envoyer 01 au serveur
	$parm['PBX_RANG'] = str_pad(intval($parm['PBX_RANG']), 2, '0', STR_PAD_LEFT);

	$parm['PBX_OUTPUT'] = "C"; // recuperer uniquement les hidden
	$parm['PBX_LANGUE'] = "FRA";
	$parm['PBX_DEVISE'] = (string)$devise_info['code_num'];
	$parm['PBX_TOTAL'] = $montant;
	$parm['PBX_PORTEUR'] = $mail;
	$parm['PBX_CMD'] = intval($id_transaction);

	// on renseigne un PBX_SHOPPINGCART qui est obligatoire, mais avec le minimum requis
	$parm['PBX_SHOPPINGCART'] = '<'.'?xml version=\'1.0\' encoding=\'utf-8\'?'."><shoppingcart><total><totalQuantity>1</totalQuantity></total></shoppingcart>";

	// decouper l'adresse en 2 parties uniquement
	$lignes = explode("\n", $billing['adresse']);
	$adresse1 = array_shift($lignes);
	$adresse2 = count($lignes) ? implode(' ', $lignes) : '';

	// mettre les différents éléments de l'adresse aux formats imposés
	// le charset va etre transliterre et on aura que de l'ascii en sortie
	$prenom 		= paybox_format_ans($billing['prenom'], 22, true);
	$nom 			= paybox_format_ans($billing['nom'], 22, true);
	$adresse1 		= paybox_format_ans($adresse1, 50, false);
	$adresse2 		= paybox_format_ans($adresse2, 50, false);
	$cp 			= paybox_format_ans($billing['code_postal'], 16, false);
	$city 			= paybox_format_ans($billing['ville'], 50, false);
	$code_pays_num  = bank_code_pays($billing['pays'], 'iso_num');

	// ajouter téléphone + indicatif si ils sont présents et OK sinon récup de la config par défaut
	$telephone = $indicatif = false;
	if (!empty($billing['telephone'])) {
		$telephone = paybox_format_tel($billing['telephone']);
	}
	if (!$telephone) {
		$telephone = lire_config('bank_paiement/config_paybox')['telephone_defaut'];
	} 
	if (!empty($billing['indicatif'])) {
		$indicatif = paybox_format_indicatif($billing['indicatif']);
	}
	if (!$indicatif) {
		$indicatif = lire_config('bank_paiement/config_paybox')['indicatif_defaut'];
	}

	$parm['PBX_BILLING'] = '<'.'?xml version=\'1.0\' encoding=\'utf-8\'?'.'><Billing><Address>'
		."<FirstName>$prenom</FirstName>"
		."<LastName>$nom</LastName>"
		."<Address1>$adresse1</Address1>"
		.($adresse2 ? "<Address2>$adresse2</Address2>" : '')
		."<ZipCode>$cp</ZipCode>"
		."<City>$city</City>"
		."<CountryCode>$code_pays_num</CountryCode>"
		."<CountryCodeMobilePhone>$indicatif</CountryCodeMobilePhone>"
		."<MobilePhone>$telephone</MobilePhone>"
		."</Address></Billing>";

	// temps de validite de la page de paiement paybox (par defaut 900s)
	if (defined('_PBX_DISPLAY')){
		$parm['PBX_DISPLAY'] = _PBX_DISPLAY;
	}

	$parm['PBX_EFFECTUE'] = bank_url_api_retour($config, "response");
	$parm['PBX_REFUSE'] = bank_url_api_retour($config, "cancel");
	$parm['PBX_ANNULE'] = bank_url_api_retour($config, "cancel");
	$parm['PBX_REPONDRE_A'] = bank_url_api_retour($config, "autoresponse");

	$parm['PBX_RETOUR'] = 'montant:M;id_transaction:R;auth:A;trans:S;abo:B;erreur:E;carte:C;BIN6:N;valid:D;3DS:c;';

	if ($type=='abo' AND $config['type']!=='acte'){
		// on decrit l'echeance, en indiquant qu'on peut la gerer manuellement grace a PayBoxDirectPlus
		if (
			$decrire_echeance = charger_fonction("decrire_echeance", "abos", true)
			AND $echeance = $decrire_echeance($id_transaction, false)){
			if ($echeance['montant']>0){
				// passage en centimes et formattage
				$montant_echeance = bank_formatter_montant_selon_fraction($echeance['montant'], $devise_info['fraction'], 10);

				// si plus d'une echeance initiale prevue on ne sait pas faire avec Paybox
				if (isset($echeance['count_init']) AND $echeance['count_init']>1){
					spip_log("Transaction #$id_transaction : nombre d'echeances init " . $echeance['count_init'] . ">1 non supporte", $mode . _LOG_ERREUR);
					return false;
				}

				// infos de l'abonnement :
				// montant identique recurrent, frequence mensuelle ou annuelle, a date anniversaire, sans delai
				$freq = "01";
				if (isset($echeance['freq']) AND $echeance['freq']=='yearly'){
					$freq = "12";
				}
				$nbpaie = "00";
				if (isset($echeance['count']) AND $n = intval($echeance['count'])){
					// paybox ne compte pas la premiere echeance, donc comptee ici (car pas dans count_init) il faut la deduire
					if (!isset($echeance['count_init']) OR !$echeance['count_init']){
						$n--;
					}
					if ($n AND $n<100){
						$nbpaie = str_pad($n, 2, "0", STR_PAD_LEFT);
					}
				}
				$parm['PBX_CMD'] .=
					"IBS_2MONT$montant_echeance"
					. "IBS_NBPAIE$nbpaie"
					. "IBS_FREQ$freq"
					. "IBS_QUAND00"//. "IBS_DELAIS000"
				;
			} else {
				$parm['PBX_RETOUR'] .= 'ppps:U;';
			}
		}
	}

	// fermer le retour avec la signature
	$parm['PBX_RETOUR'] .= 'sign:K';
	//var_dump($parm);


	include_spip('inc/filtres_mini'); // url_absolue
	$contexte = array(
		'hidden' => array(),
		'action' => paybox_url_paiment($config),
		'backurl' => url_absolue(self()),
		'id_transaction' => $id_transaction
	);

	// forcer le type de config pour n'avoir que les cartes possibles en cas d'abonnement
	$config['type'] = $type;
	$cartes_possibles = paybox_available_cards($config);
	foreach ($cartes as $carte){
		if (isset($cartes_possibles[$carte])){
			$parm_carte = $parm;
			$parm_carte['PBX_TYPEPAIEMENT'] = 'CARTE';
			$parm_carte['PBX_TYPECARTE'] = $carte;
			if ($carte === 'AMEX' and isset($parm_carte['PBX_BILLING'])) {
				include_spip('inc/charsets');
				$parm_carte['PBX_BILLING'] = translitteration_rapide($parm_carte['PBX_BILLING'], 'utf-8');
			}
			$contexte['hidden'][$carte] = paybox_form_hidden($parm_carte);
			$contexte['logo'][$carte] = $cartes_possibles[$carte];
		}
	}

	return $contexte;
}
