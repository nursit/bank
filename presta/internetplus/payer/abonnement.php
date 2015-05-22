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

include_spip('presta/internetplus/inc/wha_services');

function presta_internetplus_payer_abonnement_dist($config, $id_transaction, $transaction_hash){

	include_spip('inc/bank');

	// on decrit l'echeance
	if ($decrire_echeance = charger_fonction("decrire_echeance","abos",true)
	  AND $echeance = $decrire_echeance($id_transaction)){
		if ($echeance['montant']<=0
			OR $echeance['montant']>=30){

			spip_log("Payer abo transaction #$id_transaction : montant non pris en charge " . $echeance['montant'],"internetplus_abo"._LOG_INFO_IMPORTANTE);
			return false;
		}

		if (!isset($echeance['wha_oid']) OR !$echeance['wha_oid']){
			spip_log("Payer abo transaction #$id_transaction : pas de wha_oid dans l'echeance " . var_export($echeance,true),"internetplus_abo"._LOG_INFO_IMPORTANTE);
			return false;
		}

		// on ne gere pas les subtilites d'abonnements (frequence, count, count_init)
		// c'est l'offre wha_oid qui les porte eventuellement (dans la mesure de ce qui est possible ches InternetPlus)

	}
	else {
		spip_log("Payer abo transaction #$id_transaction : echeance inconnue","internetplus_abo"._LOG_ERREUR);
		return false;
	}

	$url_payer = wha_url_abonnement($echeance['wha_oid'],$id_transaction,$config);

	return recuperer_fond('presta/internetplus/payer/abonnement',
		array(
			'id_transaction' => $id_transaction,
			'transaction_hash' => $transaction_hash,
			'url_payer' => $url_payer,
			'logo' => wha_logo_detecte_fai_visiteur(),
			'sandbox' => wha_is_sandbox($config),
		)
	);
}