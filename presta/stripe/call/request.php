<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2015 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;


include_spip('presta/stripe/inc/stripe');

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
 * @return array
 */
function presta_stripe_call_request_dist($id_transaction, $transaction_hash, $config, $type="acte"){

	$mode = 'stripe';
	if (!is_array($config) OR !isset($config['type']) OR !isset($config['presta'])){
		spip_log("call_request : config invalide ".var_export($config,true),$mode._LOG_ERREUR);
		return "";
	}
	$mode = $config['presta'];

	if (!$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash))){
		spip_log("call_request : transaction $id_transaction / $transaction_hash introuvable",$mode._LOG_ERREUR);
		return "";
	}

	if (!$row['id_auteur']
	  AND isset($GLOBALS['visiteur_session']['id_auteur'])
	  AND $GLOBALS['visiteur_session']['id_auteur']) {
		sql_updateq("spip_transactions",
			array("id_auteur" => intval($row['id_auteur'] = $GLOBALS['visiteur_session']['id_auteur'])),
			"id_transaction=" . intval($id_transaction)
		);
	}

	// si c'est un abonnement, verifier qu'on saura le traiter vu les limitations de Stripe
	// c'est un abonnement
	if ($type === 'abo'){
		// on decrit l'echeance
		if (
			$decrire_echeance = charger_fonction("decrire_echeance","abos",true)
		  AND $echeance = $decrire_echeance($id_transaction)){
			if ($echeance['montant']>0){

				// si plus d'une echeance initiale prevue on ne sait pas faire avec Stripe
				if (isset($echeance['count_init']) AND $echeance['count_init']>1){
					spip_log("Transaction #$id_transaction : nombre d'echeances init ".$echeance['count_init'].">1 non supporte",$mode._LOG_ERREUR);
					return "";
				}
				
				// si nombre d'echeances limitees, on ne sait pas faire avec Stripe
				if (isset($echeance['count']) AND $echeance['count']>0){
					spip_log("Transaction #$id_transaction : nombre d'echeances ".$echeance['count'].">0 non supporte",$mode._LOG_ERREUR);
					return "";
				}

				if (isset($echeance['date_start']) AND $echeance['date_start'] AND strtotime($echeance['date_start'])>time()){
					spip_log("Transaction #$id_transaction : date_start ".$echeance['date_start']." non supportee",$mode._LOG_ERREUR);
					return "";
				}

			}
		}
	}
	
	
	$email = bank_porteur_email($row);

	// passage en centimes d'euros : round en raison des approximations de calcul de PHP
	$montant = intval(round(100*$row['montant'],0));
	if (strlen($montant)<3)
		$montant = str_pad($montant,3,'0',STR_PAD_LEFT);

	include_spip('inc/filtres_mini'); // url_absolue

	$contexte = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction_hash,
	);
	if ($type === 'abo'){
		$contexte['abo'] = 1;
	}
	$contexte['sign'] = bank_sign_response_simple($config['presta'], $contexte);

	$action = bank_url_api_retour($config,"response");
	foreach($contexte as $k=>$v){
		$action = parametre_url($action, $k, $v);
	}
	$contexte['action'] = $action;
	$contexte['email'] = $email;
	$contexte['amount'] = $montant;
	$contexte['currency'] = 'eur';
	$contexte['key'] = ($config['mode_test']?$config['PUBLISHABLE_KEY_test']:$config['PUBLISHABLE_KEY']);
	$contexte['name'] = textebrut($GLOBALS['meta']['nom_site']);
	$contexte['image'] = '';
	$contexte['description'] = _T('bank:titre_transaction') . '#'.$id_transaction;

	$chercher_logo = charger_fonction('chercher_logo','inc');
	if ($logo = $chercher_logo(0,'site')){
		$logo = reset($logo);
		$contexte['image'] = url_absolue($logo);
	}

	return $contexte;
}
