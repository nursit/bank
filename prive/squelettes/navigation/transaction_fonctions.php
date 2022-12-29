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


function bank_inserer_dans_boite_infos($boite_infos, $ins) {
	$p = strrpos($boite_infos, "</div>");
	$boite_infos = substr_replace($boite_infos, $ins, $p, 0);
	return $boite_infos;
}
