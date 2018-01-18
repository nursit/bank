<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2018 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Resiliation d'un abo quand c'est le site qui est maitre :
 * On declenche abos/resilier de l'API abos si elle existe
 * charge a elle de declencher prest/xxx/call/resilier_abonnement si elle existe
 *
 * @param null|string $uid
 */
function action_bank_resilier_abo_dist($uid=null){
	if (is_null($uid)){
		$securiser_action = charger_fonction('securiser_action','inc');
		$uid = $securiser_action();
	}

	if ($resilier = charger_fonction('resilier','abos',true)){
		$resilier("uid:$uid");
	}
}