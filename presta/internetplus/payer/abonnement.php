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

include_spip('presta/internetplus/inc/wha_services');

function presta_internetplus_payer_abonnement_dist($id_transaction,$transaction_hash){

	$decrire_echeance = charger_fonction("decrire_echeance","abos");
	// on decrit l'echeance, en indiquant qu'on peut la gerer manuellement grace a PayBoxDirectPlus
	if ($echeance = $decrire_echeance($id_transaction)){
		if ($echeance['montant']<=0
			OR $echeance['montant']>=30){

			spip_log("Payer abo transaction #$id_transaction : montant non pris en charge " . $echeance['montant'],"internetplus_abo"._LOG_INFO_IMPORTANTE);
			return false;
		}

		if (!isset($echeance['wha_oid'])){
			spip_log("Payer abo transaction #$id_transaction : pas de wha_oid dans l'echeance " . var_export($echeance,true),"internetplus_abo"._LOG_INFO_IMPORTANTE);
			return false;
		}

	}
	else {
		spip_log("Payer abo transaction #$id_transaction : echeance inconnue","internetplus_abo"._LOG_ERREUR);
		return false;
	}

	$url_payer = wha_url_abonnement($echeance['wha_oid'],$id_transaction,_WHA_ABO_MERCHANT_ID,_WHA_ABO_KEY_ID);

	return recuperer_fond('presta/internetplus/payer/abonnement',
		array(
			'id_transaction' => $id_transaction,
			'transaction_hash' => $transaction_hash,
			'url_payer' => $url_payer,
			'logo' => wha_logo_detecte_fai_visiteur(),
			'sandbox' => defined('_INTERNETPLUS_SANDBOX')?' ':'',
		)
	);
}