<?php

if (!function_exists('abos_decrire_echeance')) {
	function abos_decrire_echeance($id_transaction) {
	        $transaction = sql_fetsel('*', 'spip_transactions', 'id_transaction='.intval($id_transaction));

	        $desc = array(
	                'montant' => $transaction['montant'],
	                'montant_init' => $transaction['montant'],
	                'count_init' => 0, // c'est deja une echeance, par defaut
	                'count' => 0, // indefiniment
	                'freq' => 'monthly',
	                'date_start' => '',
	                'wha_oid' => '',
	        );


	        if (in_array($transaction['parrain'],['daily', 'weekly'])) {
	                $desc['freq'] = $transaction['parrain'];
	        }
	        return $desc;
	}
}
