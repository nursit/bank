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

function autoriser_simu_utilisermodepaiement_dist($faire, $mode='', $id=0, $qui = NULL, $opt = NULL){
	if (defined('_SIMU_BANK_ALLOWED') AND _SIMU_BANK_ALLOWED) return true;
	return false;
}
function autoriser_simu_utilisermodepaiementabo_dist($faire, $mode='', $id=0, $qui = NULL, $opt = NULL){
	if (defined('_SIMU_BANK_ALLOWED') AND _SIMU_BANK_ALLOWED) return true;
	return false;
}
