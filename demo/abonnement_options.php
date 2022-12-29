<?php
/**
 * Fichier de test : inclure ce fichier depuis votre fichier mes_options pour tester les abonnements
 * les deux fonctinos vont prendre la main sur le process normal et decrire un abonnement mensuel de montant fixe
 * identique à celui de la transaction de départ
 */


if (!function_exists('abos_decrire_echeance')) {
	function abos_decrire_echeance($id_transaction) {
	        $transaction = sql_fetsel('*', 'spip_transactions', 'id_transaction='.intval($id_transaction));

	        $desc = array(
	                'montant' => $transaction['montant'],
	                'montant_init' => $transaction['montant'],
	                'count_init' => 0, // c'est deja une echeance, par defaut
	                'count' => 0, // indefiniment
	                'freq' => 'monthly', // daily, monthly ou yearly
	                'date_start' => '',
	                'wha_oid' => '',
	        );


	        if (in_array($transaction['parrain'],['daily', 'weekly'])) {
	                $desc['freq'] = $transaction['parrain'];
	        }
	        return $desc;
	}
}
if (!function_exists('abos_preparer_echeance')){
	function abos_preparer_echeance($id){

		spip_log("abos/preparer_echeance id=$id", "bank" . _LOG_DEBUG);

		$where = "0=1";
		if (strncmp($id,"uid:",4)==0){
			$where = "abo_uid=".sql_quote(substr($id,4));
		}

		// retrouver la transaction d'origine de l'abonnement
		if ($transaction = sql_fetsel('*', 'spip_transactions', "reglee='oui' AND $where", '', 'date_transaction', '0,1')) {


			$montant = $transaction['montant'];
			$options = [
				'montant_ht' => $transaction['montant_ht'],
				'devise' => $transaction['devise'],
				'id_auteur' => $transaction['id_auteur'],
				'auteur_id' => $transaction['auteur_id'],
				'auteur' => $transaction['auteur'],
				'parrain' => $transaction['parrain'],
				'tracking_id' => $transaction['tracking_id'],
				'champs' => [
					'abo_uid' => $id,
				]
			];

			$inserer_transaction = charger_fonction('inserer_transaction', 'bank');
			if ($id_transaction = $inserer_transaction($montant, $options)){
				spip_log("abos/preparer_echeance id=$id transaction $id_transaction ajoutee", "bank" . _LOG_DEBUG);
				return $id_transaction;
			}
		}


		return false;
	}
}
