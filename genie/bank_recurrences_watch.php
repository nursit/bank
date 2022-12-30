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

function genie_bank_recurrences_watch_dist($t) {


	include_spip('inc/bank_recurrences');

	// on se laisse 20s et pas la peine d'essayer d'en faire plus de 20...
	$timeout = $_SERVER['REQUEST_TIME'] + 20;
	$nbmax = 20;

	$actions = bank_recurrences_watch_lister_actions($nbmax, $timeout);
	if (empty($actions)) {
		return 1; // rien a faire ce coup ci
	}

	$nb = count($actions);
	spip_log("genie_bank_recurrences_watch_dist: $nb actions", "recurrences" . _LOG_DEBUG);

	while (time() > $timeout and count($actions)) {
		$action = array_shift($actions);
		$display = implode(' ', $action);
		spip_log("genie_bank_recurrences_watch_dist: $display", "recurrences" . _LOG_DEBUG);
		$callback = array_shift($action);
		$res = call_user_func_array($callback, $action);
		if ($res === false) {
			spip_log("genie_bank_recurrences_watch_dist: Echec de $display", "recurrences" . _LOG_ERREUR);
		}
		else {
			spip_log("genie_bank_recurrences_watch_dist: $display OK", "recurrences" . _LOG_DEBUG);
		}
	}

	// on a pas fini on redemande la main
	// pour etre prioritaire lors du cron suivant
	return (0 - $t);
}
