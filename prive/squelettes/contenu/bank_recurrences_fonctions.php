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

function bank_recurrences_statuts(){
	$statuts = sql_allfetsel("statut, count(id_bank_recurrence) as n", "spip_bank_recurrences", "", "statut");
	if ($statuts){
		$statuts = array_column($statuts, 'n', 'statut');
		ksort($statuts);
	} else {
		$statuts = array('valide' => 0);
	}

	$all = array('' => array_sum($statuts));
	foreach (array('valide', 'prepa', 'fini', 'echec') as $s){
		if (isset($statuts[$s])){
			$all[$s] = $statuts[$s];
			unset($statuts[$s]);
		}
	}
	return $all;
}
