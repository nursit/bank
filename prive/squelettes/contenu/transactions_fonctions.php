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

function bank_transactions_statuts(){
	$statuts = sql_allfetsel("statut, count(id_transaction) as n","spip_transactions","","statut");
	$statuts = array_combine(array_map('reset',$statuts),array_map('end',$statuts));
	ksort($statuts);

	$all = array(''=>array_sum($statuts));
	foreach(array('ok','commande','attente') as $s){
		if (isset($statuts[$s])) {
			$all[$s] = $statuts[$s];
			unset($statuts[$s]);
		}
	}
	$all['echec'] = 0;
	$all['abandon'] = 0;
	$all['rembourse'] = 0;
	foreach($statuts as $k=>$v){
		if (strncmp($k,"echec",5)==0){
			if (!isset($all['echec'])) $all['echec'] = 0;
			$all['echec']+=$v;
		}
		else {
			$all[$k] = $v;
		}
	}
	return $all;
}