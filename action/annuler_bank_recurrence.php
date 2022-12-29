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

function action_annuler_bank_recurrence_dist($id_bank_recurrence = null, $immediat = false){

	if (is_null($id_bank_recurrence)){
		$securiser_action = charger_fonction('securiser_action', 'inc');
		$arg = $securiser_action();
		$arg = explode('-', $arg);
		$id_bank_recurrence = array_shift($arg);
		$immediat = false;
		if (count($arg)) {
			$immediat = array_shift($arg);
		}
	}
	if (autoriser('annuler', 'bank_recurrence', $id_bank_recurrence)) {
		if ($id_bank_recurrence = intval($id_bank_recurrence)
			AND $row = sql_fetsel("*", "spip_bank_recurrences", "id_bank_recurrence=" . intval($id_bank_recurrence))
			and !empty($row["uid"])
			AND $row["statut"]=="valide"){

			include_spip('inc/bank_recurrences');
			$immediat = ($immediat ? true : false);
			bank_recurrence_terminer($row["uid"], 'fini', $immediat);
		}
	}
}
