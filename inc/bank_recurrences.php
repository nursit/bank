<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2022 - Distribue sous licence GNU/GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

include_spip('inc/bank');

/**
 * @uses bank_recurrence_terminer()
 * @uses bank_recurrence_renouveler()
 * @param int $max_items
 * @param null $timeout
 * @return array
 */
function bank_recurrences_watch_lister_actions($max_items = 0, $timeout = null) {
	// trouver les recurrences à renouveler/terminer et
	$now = date('Y-m-d H:i:s');
	$now_fin_journee = date('Y-m-d 23:59:59');
	$actions = [];

	$termine_expr = 'date_fin_prevue>\'0001-01-01\' AND date_fin_prevue<='.sql_quote($now);
	$limit = ($max_items ? "0,$max_items" : '');
	$res = sql_select(
		"uid, ($termine_expr) as termine",
		'spip_bank_recurrences',
		"statut='valide' AND id_transaction_echeance_next=0 AND (($termine_expr) OR date_echeance_next<=" . sql_quote($now_fin_journee) . ")",
		'',
		'termine DESC, date_echeance_next',
		$limit
	);
	while ($recurrence = sql_fetch($res)) {
		if ($recurrence['termine']) {
			$actions[] = ['bank_recurrence_terminer', $recurrence['uid']];
		}
		else {
			$actions[] = ['bank_recurrence_renouveler', $recurrence['uid']];
		}

		if ($timeout and time()>$timeout) {
			sql_free($res);
			return $actions; //on renvoie ce qu'on a deja pu lister
		}
	}

	return $actions;
}

/**
 * Generer le message d'erreur d'une recurrence invalide
 * avec log et email eventuel
 *
 * @param int|string $id_transaction
 * @param array $args
 *   string mode : mode de paiement
 *   string erreur :  texte en clair de l'erreur
 *   string log : texte complementaire pour les logs
 *   bool send_mail : avertir le webmestre par mail
 *   string sujet : sujet du mail
 *   bool update : mettre a jour la transaction en base ou non (false par defaut)
 * @return array
 */
function bank_recurrence_invalide($id_transaction = "", $args = array()) {
	$default = array(
		'mode' => 'recurrence',
		'erreur' => '',
		'log' => '',
		'send_mail' => true,
		'sujet' => 'Echec recurrence',
		'update' => false,
		'where' => 'bank_recurrence_creer',
	);
	$args = array_merge($default, $args);
	$args['update'] = false; // ne surtout rien faire à la transaction
	return bank_transaction_invalide($id_transaction, $args);
}

/**
 * Générer un uid abonnement hexa unique qui contient $id_bank_recurrence et $id_transaction initiale
 * on gère la collision au cas où mais elle ne peut théoriquement pas se produire
 * @param int $id_bank_recurrence
 * @param int $id_transaction
 * @return string
 */
function bank_recurrence_generate_uid($id_bank_recurrence, $id_transaction) {
	$cpt = 0;
	do {
		$uid = "sub_";
		$parts = [$id_bank_recurrence, $id_transaction];
		if ($cpt) {
			$parts[] = $cpt;
		}
		$parts = array_map('dechex',$parts);
		$parts = implode('.', $parts);
		if ($d = gzdeflate($parts)) {
			$parts = $d;
		}
		$uid .= bin2hex($parts);
		$cpt++;
	} while (sql_countsel('spip_bank_recurrences', ['uid='.sql_quote($uid),"id_bank_recurrence!=".intval($id_bank_recurrence)]));
	return $uid;
}

/**
 * Décoder un uid abonnement (et vérification de sa validité du coup)
 * @param string $uid
 * @return array|false
 */
function bank_recurrence_decode_uid($uid) {
	if (strpos($uid, 'sub_') !== 0) {
		return false;
	}
	$hash = substr($uid, 4);

	$hash = hex2bin($hash);
	if ($d = gzinflate($hash)) {
		$hash = $d;
	}
	$parts = explode('.', $hash);
	if (count($parts) <2 or count($parts) > 3) {
		return false;
	}
	foreach ($parts as $part) {
		// verifier que chaque part est un hexa
		if (preg_match(',[^0-9a-f],i', $part)) {
			return false;
		}
	}
	$parts = array_map('hexdec',$parts);
	$id_bank_recurrence = array_shift($parts);
	$id_transaction = array_shift($parts);
	if (!$id_bank_recurrence or !$id_transaction) {
		return false;
	}
	return ['id_bank_recurrence' => $id_bank_recurrence, 'id_transaction' => $id_transaction];
}

/**
 * Calculer les champs à mettre à jour pour la prochaine échéance : date_echeance_next et date_fin éventuelle
 * 2 methodes de calcul :
 * - depuis la date de départ (a priori c'est celle qu'on utilise)
 * - depuis la dernière échéance
 *
 * @param array $echeances
 * @param string $date_start
 * @param string $date_echeance
 * @param int $count_echeance
 * @param string $date_fin
 * @param bool $count_from_start = true
 * @return array|false
 */
function bank_recurrence_calculer_echeance_next($echeances, $date_start, $date_echeance, $count_echeance, $date_fin, $count_from_start = true) {
	if (is_string($echeances)) {
		$echeances = json_decode($echeances, true);
	}
	if (empty($echeances)) {
		return false;
	}
	$freq = "monthly";
	if (isset($echeances['freq'])){
		$freq = strtolower($echeances['freq']);
	}
	if (!in_array($freq, array('daily', 'monthly', 'yearly'))) {
		return false;
	}
	$t_last_echeance = strtotime($date_echeance);
	if (!$t_last_echeance) {
		return false;
	}
	$t_first_echeance = strtotime($date_start);
	if (!$t_first_echeance) {
		return false;
	}
	// $count_echeance = 1 c'est le date_start
	// date_echeance doit dont etre date_start + ($count_echeance-1) * periode
	// et date_echeance_next doit dont etre date_start + $count_echeance- * periode
	switch ($freq) {
		case 'daily':
			if ($count_from_start) {
				$nbdays = $count_echeance;
				$date_next_echeance = date('Y-m-d H:i:s', strtotime("+{$nbdays} days", $t_first_echeance));
			}
			else {
				$date_next_echeance = date('Y-m-d H:i:s', strtotime('+1day', $t_last_echeance));
			}
			break;
		case 'monthly':
			// cas particulier : c'est +1 mois mais on veut rester sur le jour anniversaire du depart ou les précédents si les mois sont plus courts
			// pour garder un rythme "1 paiement par mois"
			$d = date('d', strtotime($date_start));
			if ($count_from_start) {
				$nbmonth = $count_echeance;
				$nbyears = intval(floor($nbmonth / 12));
				$nbmonth -= $nbyears * 12;

				// d'abord on calcule en prenant un debut de mois en point de depart
				$t_first_echeance_debut_mois = strtotime(date('Y-m-01 H:i:s', $t_first_echeance));
				$t_next_echeance_debut_mois = strtotime("+{$nbyears} years", $t_first_echeance_debut_mois);
				$t_next_echeance_debut_mois = strtotime("+{$nbmonth} months", $t_next_echeance_debut_mois);
				$next_month = date('Y-m', $t_next_echeance_debut_mois);
				// puis on force le même jour que le point de depart et on corrige eventuellement si ça déborde du mois concerné
				$date_next_echeance = $next_month . "-{$d} " . date('H:i:s', $t_first_echeance);
				$date_next_echeance = date('Y-m-d H:i:s', strtotime($date_next_echeance));
				while (strpos($date_next_echeance, $next_month) !== 0) {
					$date_next_echeance = strtotime('-1day', strtotime($date_next_echeance));
					$date_next_echeance = date('Y-m-d H:i:s', $date_next_echeance);
				}
			}
			else {
				$this_month = strtotime(date('Y-m-01 H:i:s', $t_last_echeance));
				$next_month = date('Y-m', strtotime('+1month', $this_month));
				$date_next_echeance = $next_month . "-{$d} " . date('H:i:s', $t_last_echeance);
				$date_next_echeance = date('Y-m-d H:i:s', strtotime($date_next_echeance));
				while (strpos($date_next_echeance, $next_month) !== 0) {
					$date_next_echeance = strtotime('-1day', strtotime($date_next_echeance));
					$date_next_echeance = date('Y-m-d H:i:s', $date_next_echeance);
				}
			}
			break;
		case 'yearly':
			if ($count_from_start) {
				$nbyears = $count_echeance;
				$date_next_echeance = date('Y-m-d H:i:s', strtotime("+{$nbyears} years", $t_first_echeance));
			}
			else {
				$date_next_echeance = date('Y-m-d H:i:s', strtotime('+1year', $t_last_echeance));
			}
			break;
	}
	$set = array(
		'date_echeance_next' => $date_next_echeance,
		'id_transaction_echeance_next' => 0,
	);
	// si jamais on a atteint le nombre maxi d'echeances, alors la date theorique de la prochaine c'est la date de fin
	if (!empty($echeances['count'])) {
		$nb_max_echeances = $echeances['count'];
		if (!empty($echeances['count_init'])) {
			$nb_max_echeances += $echeances['count_init'];
		}
		if ($count_echeance >= $nb_max_echeances) {
			if (!intval($date_fin) or strtotime($date_fin) > strtotime($date_next_echeance)) {
				$set['date_fin_prevue'] = $date_next_echeance;
			}
		}
	}

	return $set;
}

/**
 * Créer une recurrence en statut prepa, lors de la préparation d'un paiement qui va démarrer un abonnement
 *
 * @param int $id_transaction
 * @param string $mode
 * @param array $echeance
 * @return false|string
 */
function bank_recurrence_creer($id_transaction, $mode, $echeance = null) {
	$abo_uid = "";

	if (is_null($echeance)) {
		if (!$decrire_echeance = charger_fonction("decrire_echeance", "abos", true)
			or !$echeance = $decrire_echeance($id_transaction)) {
			spip_log("bank_recurrence_creer: Abonnement Transaction #$id_transaction impossible d'obtenir la description des echeances", $mode . _LOG_ERREUR);
			return false;
		}
	}

	// on ne sait pas faire une date de debut decalee dans le futur
	// TODO a prevoir dans le futur
	if (isset($echeance['date_start'])
	  and $echeance['date_start']
	  and strtotime($echeance['date_start'])>time()){
		spip_log("bank_recurrence_creer: Abonnement Transaction #$id_transaction : date_start " . $echeance['date_start'] . " non supportee", $mode . _LOG_ERREUR);
		return false;
	}

	// dans tous les cas on créé la récurrence en base en mode prepa, sauf si elle existe déjà
	if (!$recurrence = sql_fetsel('*', 'spip_bank_recurrences', 'id_transaction='.intval($id_transaction))) {
		$ins = array(
			'id_transaction' => $id_transaction,
			'statut' => 'prepa',
			'date_creation' => date('Y-m-d H:i:s'),
			'echeances' => json_encode($echeance)
		);
		$id_bank_recurrence = sql_insertq('spip_bank_recurrences', $ins);
		if (!$id_bank_recurrence) {
			spip_log("bank_recurrence_creer: Abonnement Transaction #$id_transaction : impossible de créer la recurrence en base dans spip_bank_recurrences", $mode . _LOG_ERREUR);
			return false;
		}
		bank_recurrence_tracer($id_bank_recurrence, "création de la récurrence selon transaction #$id_transaction " . json_encode($ins));
		$recurrence = sql_fetsel('*', 'spip_bank_recurrences', 'id_bank_recurrence=' . intval($id_bank_recurrence));
	}
	else {
		$id_bank_recurrence = $recurrence['id_bank_recurrence'];
	}

	// verifier qu'on est bien en statut prepa, sinon problème
	if ($recurrence['statut'] !== 'prepa') {
		spip_log("bank_recurrence_creer: Abonnement Transaction #$id_transaction : recurrence $id_bank_recurrence statut inatendu '".$recurrence['statut']."'", $mode . _LOG_ERREUR);
		return false;
	}

	// générer un numéro d'abonnement unique
	if (empty($recurrence['uid'])
	  or !$decode = bank_recurrence_decode_uid($recurrence['uid'])
	  or $id_bank_recurrence != $decode['id_bank_recurrence']
	  or $id_transaction != $decode['id_transaction']) {
		$uid = bank_recurrence_generate_uid($id_bank_recurrence, $id_transaction);
		$set = array(
			'uid' => $uid,
		);
		if (!sql_updateq('spip_bank_recurrences', $set, 'id_bank_recurrence=' . intval($id_bank_recurrence))) {
			return false;
		}
		bank_recurrence_tracer($id_bank_recurrence, "generation d'un UID abonnement uid=$uid");
	}
	else {
		$uid = $recurrence['uid'];
	}

	return $uid;
}

/**
 * Activer la récurrence suite au premier paiement réussi, et actualiser la date de prochaine échéance
 *
 * @param int $id_transaction
 * @param string $abo_uid
 * @param string $mode
 * @param ?string $payment_data
 * @return bool|mixed
 */
function bank_recurrence_activer($id_transaction, $abo_uid, $mode, $payment_data = null) {
	if (!$recurrence = sql_fetsel(
		'*',
		'spip_bank_recurrences',
		'uid='.sql_quote($abo_uid) . ' AND id_transaction='.intval($id_transaction))) {

		spip_log("bank_recurrence_activer: Abonnement Transaction #$id_transaction / $abo_uid introuvable", $mode . _LOG_ERREUR);
		return false;
	}

	if ($recurrence['statut'] === 'valide') {
		// deja activé, double hit au retour du paiement
		return true;
	}

	$id_bank_recurrence = $recurrence['id_bank_recurrence'];

	// verifier qu'on est bien en statut prepa, sinon problème
	if ($recurrence['statut'] !== 'prepa') {
		spip_log("bank_recurrence_activer: Abonnement Transaction #$id_transaction : recurrence $id_bank_recurrence statut inatendu '".$recurrence['statut']."'", $mode . _LOG_ERREUR);
		return false;
	}

	$transaction = sql_fetsel('*', 'spip_transactions', 'id_transaction='.intval($id_transaction));
	if (!$transaction) {
		spip_log("bank_recurrence_activer: Abonnement Transaction #$id_transaction : recurrence $id_bank_recurrence, impossible de relire la transaction en base", $mode . _LOG_ERREUR);
		return false;
	}

	// initialiser les echeances
	$now = $_SERVER['REQUEST_TIME'];
	$set = [
		'count_echeance' => 1,
		'date_start' => date('Y-m-d H:i:s', $now),
		'date_echeance' => date('Y-m-d H:i:s', $now),
		'id_transaction_echeance' => $id_transaction,
	];
	if (!empty($payment_data)) {
		$set['payment_data'] = $payment_data;
	}

	$validite = null;
	$date_fin = $recurrence['date_fin_prevue'];
	if (!empty($transaction['validite'])) {
		$validite = $transaction['validite'];
		// placer la date de fin au 01 du mois qui suit le mois de fin de validite (car on peut faire un paiement jusqu'au dernier jour du mois, 23h59)
		$date_fin_validite = strtotime('+4days',strtotime($validite . "-28"));
		$date_fin_validite = date('Y-m-01 00:00:00', $date_fin_validite);
		if (!intval($date_fin) or $date_fin_validite < $date_fin) {
			$date_fin = $date_fin_validite;
			$set['date_fin_prevue'] = $date_fin;
		}
	}

	$set_echeance = bank_recurrence_calculer_echeance_next(
		$recurrence['echeances'],
		$set['date_start'],
		$set['date_echeance'],
		$set['count_echeance'],
		$date_fin);

	if (!$set_echeance) {
		spip_log("bank_recurrence_activer: Abonnement Transaction #$id_transaction : recurrence $id_bank_recurrence, impossible de calculer la prochaine echeance", $mode . _LOG_ERREUR);
		return false;
	}

	$set = array_merge($set, $set_echeance);
	$set['statut'] = 'valide';

	sql_updateq('spip_bank_recurrences', $set, 'id_bank_recurrence='.intval($id_bank_recurrence));
	bank_recurrence_tracer($id_bank_recurrence, "activation de la récurrence statut => valide " . json_encode($set));

	if ($activer_abonnement = charger_fonction('activer_abonnement', 'abos', true)){
		$activer_abonnement($id_transaction, $abo_uid, $mode, $date_fin);
	}

	return $abo_uid;
}

/**
 * Prolonger une récurrence suite au paiement réussi de l'échéance
 *
 * @param int $id_transaction
 * @param string $abo_uid
 * @param string $mode
 * @param ?string $payment_data
 * @return false|string
 */
function bank_recurrence_prolonger($id_transaction, $abo_uid, $mode, $payment_data = null) {
	if (!$recurrence = sql_fetsel(
		'*',
		'spip_bank_recurrences',
		'uid='.sql_quote($abo_uid))) {

		spip_log("bank_recurrence_renouveler: Abonnement $abo_uid introuvable pour transaction renouvellement #$id_transaction", $mode . _LOG_ERREUR);
		return false;
	}

	if ($recurrence['statut'] !== 'valide') {
		spip_log("bank_recurrence_renouveler: Renouvellement abonnement $abo_uid impossible car statut=".$recurrence['statut'], $mode . _LOG_ERREUR);
		return false;
	}

	$id_bank_recurrence = $recurrence['id_bank_recurrence'];

	$now = time();
	$set = [
		'count_echeance' => $recurrence['count_echeance'] + 1,
		'date_echeance' => date('Y-m-d H:i:s', $now),
		'id_transaction_echeance' => $id_transaction,
	];
	if (!empty($payment_data)) {
		$set['payment_data'] = $payment_data;
	}

	$set_echeance = bank_recurrence_calculer_echeance_next(
		$recurrence['echeances'],
		$recurrence['date_start'],
		$set['date_echeance'],
		$set['count_echeance'],
		$recurrence['date_fin_prevue']);


	if (!$set_echeance) {
		spip_log("bank_recurrence_renouveler: Renouvellement abonnement $abo_uid impossible (paiement transaction #$id_transaction) : impossible de calculer la prochaine echeance", $mode . _LOG_ERREUR);
		return false;
	}
	$set = array_merge($set, $set_echeance);

	sql_updateq('spip_bank_recurrences', $set, 'id_bank_recurrence='.intval($id_bank_recurrence));
	bank_recurrence_tracer($id_bank_recurrence, "prolonger la récurrence " . json_encode($set));

	// et renouveller l'abonnement
	if ($renouveler_abonnement = charger_fonction('renouveler_abonnement', 'abos', true)){
		$renouveler_abonnement($id_transaction, $abo_uid, $mode);
	}

	return $abo_uid;
}

/**
 * Résilier une récurrence suite à echec du paiement de de l'échéance
 *
 * @param int $id_transaction
 * @param string $abo_uid
 * @param string $mode
 * @param string $statut
 * @return bool
 */
function bank_recurrence_resilier($id_transaction, $abo_uid, $mode, $statut = 'echec') {

	$ok = true;
	if (!$recurrence = sql_fetsel(
		'*',
		'spip_bank_recurrences',
		'uid='.sql_quote($abo_uid))) {

		spip_log("bank_recurrence_resilier: Abonnement $abo_uid introuvable pour transaction renouvellement #$id_transaction", $mode . _LOG_ERREUR);
		$ok = false;
	}

	if ($ok) {
		$id_bank_recurrence = $recurrence['id_bank_recurrence'];
		if (in_array($recurrence['statut'], array('prepa', 'valide'))) {

			if (!in_array($statut, array('echec', 'fini'))) {
				$statut = 'echec';
			}

			$now = time();
			$set = array(
				'date_fin' => date('Y-m-d H:i:s', $now),
				'count_echeance' => $recurrence['count_echeance'] + 1,
				'date_echeance' => date('Y-m-d H:i:s', $now),
				'id_transaction_echeance' => $id_transaction,
				'date_echeance_next' => '0000-00-00 00:00:00',
				'id_transaction_echeance_next' => 0,
				'statut' => $statut,
			);
			sql_updateq('spip_bank_recurrences', $set, 'id_bank_recurrence='.intval($id_bank_recurrence));
			bank_recurrence_tracer($id_bank_recurrence, "résiliation de la récurrence " . json_encode($set));
			spip_log("bank_recurrence_resilier: Résiliation abonnement $abo_uid statut : ".$recurrence['statut'] . " => $statut", $mode . _LOG_INFO_IMPORTANTE);
		}
		else {
			// si on est deja fini ou dans un autre etat, on sort de là
			spip_log("bank_recurrence_resilier: Résiliation abonnement $abo_uid impossible car statut=".$recurrence['statut'], $mode . _LOG_ERREUR);
			return (in_array($recurrence['statut'], array('echec', 'fini')) ? true : false);
		}
	}

	if ($resilier = charger_fonction('resilier', 'abos', true)){
		$options = array(
			'notify_bank' => false, // pas la peine : recurrence deja resilie ci-dessus
			'immediat' => true,
			'message' => "[bank] Transaction #$id_transaction refusee",
			'erreur' => true,
		);
		$resilier("uid:" . $abo_uid, $options);
	}

	return $ok;
}

/**
 * Terminer une récurrence qui arrive à la fin de ses échéances
 *
 * @param string $abo_uid
 * @param string $statut
 * @param ?bool $avec_resiliation
 *   false => ne pas lancer la resiliation abo (car on en vient déjà)
 *   null => resiliation par défaut en fin de periode en cours
 *   true => resiliation immediate
 * @return bool
 */
function bank_recurrence_terminer($abo_uid, $statut = 'fini', $avec_resiliation = true) {

	if (!$recurrence = sql_fetsel(
		'*',
		'spip_bank_recurrences',
		'uid='.sql_quote($abo_uid))) {

		spip_log("bank_recurrence_terminer: Abonnement $abo_uid introuvable pour fin recurrence", 'recurrence' . _LOG_ERREUR);
		return false;
	}

	$id_bank_recurrence = $recurrence['id_bank_recurrence'];
	if (in_array($recurrence['statut'], array('prepa', 'valide'))) {

		if (!in_array($statut, array('echec', 'fini'))) {
			$statut = 'fini';
		}

		$now = time();
		$set = array(
			'date_fin' => date('Y-m-d H:i:s', $now),
			'date_echeance_next' => '0000-00-00 00:00:00',
			'id_transaction_echeance_next' => 0,
			'statut' => $statut
		);
		sql_updateq('spip_bank_recurrences', $set, 'id_bank_recurrence='.intval($id_bank_recurrence));
		bank_recurrence_tracer($id_bank_recurrence, "fin de la récurrence " . json_encode($set));
		spip_log("bank_recurrence_terminer: Fin abonnement $abo_uid statut : ".$recurrence['statut'] . " => $statut", 'recurrence' . _LOG_INFO_IMPORTANTE);

		if ($avec_resiliation !== false) {
			// il faut résilier les fonctions d'abonnement associées au paiement récurrent
			if ($resilier = charger_fonction('resilier', 'abos', true)){
				$options = array(
					'notify_bank' => false, // pas la peine : recurrence deja resilie ci-dessus
					'immediat' => $avec_resiliation === true,
					'message' => "[bank] Récurrence $abo_uid terminée",
				);
				$resilier("uid:" . $abo_uid, $options);
			}
		}

		return true;
	}
	else {
		// si on est deja fini ou dans un autre etat, on sort de là
		spip_log("bank_recurrence_terminer: Fin abonnement $abo_uid impossible car statut=".$recurrence['statut'], 'recurrence' . _LOG_ERREUR);
		return (in_array($recurrence['statut'], array('echec', 'fini')) ? true : false);
	}

}

/**
 * Renouvler une récurrence dont l'échéance est arrivée
 *
 * @param string $abo_uid
 * @param string $statut
 * @return bool
 */
function bank_recurrence_renouveler($abo_uid) {

	if (!$recurrence = sql_fetsel(
		'*',
		'spip_bank_recurrences',
		'uid='.sql_quote($abo_uid))) {

		spip_log("bank_recurrence_renouveler: Abonnement $abo_uid introuvable pour renouveler recurrence", 'recurrence' . _LOG_ERREUR);
		return false;
	}

	$id_bank_recurrence = $recurrence['id_bank_recurrence'];
	if ($recurrence['id_transaction_echeance_next'] != 0) {
		spip_log("bank_recurrence_renouveler: Abonnement $abo_uid déjà en cours de renouvelement par un autre processus", 'recurrence' . _LOG_ERREUR);
		return false;
	}

	$id_jeton = -getmypid();
	if (!sql_updateq('spip_bank_recurrences', array('id_transaction_echeance_next' => $id_jeton), 'id_transaction_echeance_next=0 and id_bank_recurrence='.intval($id_bank_recurrence))
	  or sql_getfetsel('id_transaction_echeance_next', 'spip_bank_recurrences', 'id_bank_recurrence='.intval($id_bank_recurrence)) != $id_jeton) {
		spip_log("bank_recurrence_renouveler: Abonnement $abo_uid Recurrence #$id_bank_recurrence | impossible de poser le jeton id_transaction_echeance_next=$id_jeton", 'recurrence' . _LOG_ERREUR);
		bank_recurrence_invalide(0, array(
			'erreur' => "Abonnement $abo_uid Recurrence #$id_bank_recurrence | impossible de poser le jeton id_transaction_echeance_next=$id_jeton",
			'log' => '',
			'send_mail' => true,
			'sujet' => "Echec renouvellement recurrence #$id_bank_recurrence",
			'update' => false,
			'where' => 'bank_recurrence_renouveler',
		));
		return false;
	}

	// recuperons le mode et le presta depuis la premiere transaction de la recurrence
	if (!$mode = sql_getfetsel('mode', 'spip_transactions', 'id_transaction='.intval($recurrence['id_transaction']))
	  or !$config = bank_config($mode, true)
	  or empty($config['presta'])) {
		spip_log("bank_recurrence_renouveler: Abonnement $abo_uid Recurrence #$id_bank_recurrence | impossible de retrouver le prestaire de paiement", 'recurrence' . _LOG_ERREUR);
		bank_recurrence_invalide(0, array(
			'erreur' => "Abonnement $abo_uid Recurrence #$id_bank_recurrence | impossible de retrouver le prestaire de paiement",
			'log' => '',
			'send_mail' => true,
			'sujet' => "Echec renouvellement recurrence #$id_bank_recurrence",
			'update' => false,
			'where' => 'bank_recurrence_renouveler',
		));
		return false;
	}


	// ok ici on a le jeton, la récurrence est à nous
	if (!$preparer_echeance = charger_fonction('preparer_echeance', 'abos', true)) {
		spip_log("bank_recurrence_renouveler: Abonnement $abo_uid Recurrence #$id_bank_recurrence | aucune fonction abos/preparer_echeance disponible", 'recurrence' . _LOG_ERREUR);
		// on est en echec, on laisse le jeton sur id_transaction_echeance_next pour ne pas re-essayer à nouveau sur cette recurrence
		bank_recurrence_invalide(0, array(
			'erreur' => "Abonnement $abo_uid Recurrence #$id_bank_recurrence | aucune fonction abos/preparer_echeance disponible",
			'log' => '',
			'send_mail' => true,
			'sujet' => "Echec renouvellement recurrence #$id_bank_recurrence",
			'update' => false,
			'where' => 'bank_recurrence_renouveler',
		));
		return false;
	}

	$id_transaction = $preparer_echeance("uid:" . $abo_uid);
	if (!$id_transaction) {
		spip_log("bank_recurrence_renouveler: Abonnement $abo_uid Recurrence #$id_bank_recurrence | echec création d'une transaction pour le renouvellement", 'recurrence' . _LOG_ERREUR);
		// ici on libère le jeton, on re-essayera plus tard
		sql_updateq('spip_bank_recurrences', array('id_transaction_echeance_next' => 0), 'id_bank_recurrence='.intval($id_bank_recurrence));
		// on envoie un mail d'alerte
		bank_recurrence_invalide(0, array(
			'erreur' => "Abonnement $abo_uid Recurrence #$id_bank_recurrence | echec création d'une transaction pour le renouvellement",
			'log' => '',
			'send_mail' => true,
			'sujet' => "Echec renouvellement recurrence #$id_bank_recurrence",
			'update' => false,
			'where' => 'bank_recurrence_renouveler',
		));
		return false;
	}

	// notons l'id_transaction qu'on a préparé pour cette echeance
	sql_updateq('spip_bank_recurrences', array('id_transaction_echeance_next' => $id_transaction), 'id_bank_recurrence='.intval($id_bank_recurrence));
	bank_recurrence_tracer($id_bank_recurrence, "préparation du renouvellement de la récurrence id_transaction_echeance_next=$id_transaction");
	// et le abo_uid sur la transaction
	sql_updateq('spip_transactions', array('abo_uid' => $abo_uid), 'id_transaction='.intval($id_transaction));
	$transaction = sql_fetsel('*', 'spip_transactions', 'id_transaction='.intval($id_transaction));


	$response = array(
		'id_transaction' => $id_transaction,
		'transaction_hash' => $transaction['transaction_hash'],
		'abo' => 'recurrence',
		'abo_uid' => $abo_uid,
		'payment_data' => $recurrence['payment_data']
	);

	$call_response = charger_fonction("response", "presta/".$config['presta']."/call/");
	$res = $call_response($config, $response);

	$id_transaction = array_shift($res);
	$success = array_shift($res);

	if (!$id_transaction or !$success) {
		bank_recurrence_invalide(0, array(
			'erreur' => "Abonnement $abo_uid Recurrence #$id_bank_recurrence | echec paiement transaction #" . ($id_transaction ? $id_transaction : $response['id_transaction']),
			'log' => '',
			'send_mail' => true,
			'sujet' => "Echec renouvellement recurrence #$id_bank_recurrence",
			'update' => false,
			'where' => 'bank_recurrence_renouveler',
		));

		// resilier la recurrence : paiement échoué l'abonnement s'arrête
		bank_recurrence_resilier($response['id_transaction'], $abo_uid, 'recurrence');
		return false;
	}

	return true;
}


/**
 * Mise en forme de la trace des abonnements/desabonnements dans le champ optin
 *
 * @paraim int $id_bank_recurrence
 * @param string $action
 *   nouvelle action tracee
 * @return string
 */
function bank_recurrence_tracer($id_bank_recurrence, $action) {
	$trace = [ date('Y-m-d H:i:s'),_T('public:par_auteur')];

	if (!empty($GLOBALS['visiteur_session']['id_auteur'])) {
		$trace[] = "#" . $GLOBALS['visiteur_session']['id_auteur'];
	}
	if (!empty($GLOBALS['visiteur_session']['nom'])) {
		$trace[] = $GLOBALS['visiteur_session']['nom'];
	}
	if (!empty($GLOBALS['visiteur_session']['session_nom'])) {
		$trace[] = $GLOBALS['visiteur_session']['session_nom'];
	}
	if (!empty($GLOBALS['visiteur_session']['session_email'])) {
		$trace[] = $GLOBALS['visiteur_session']['session_email'];
	}
	if (!defined('_IS_CLI')) {
		define(
			'_IS_CLI',
			!isset($_SERVER['HTTP_HOST'])
			and !strlen($_SERVER['DOCUMENT_ROOT'])
			and !empty($_SERVER['argv'])
			and empty($_SERVER['REQUEST_METHOD'])
		);
	}
	if (_IS_CLI) {
		$trace[] = "(CLI)";
	}
	else {
		$trace[] = '(' . $GLOBALS['ip'] . ')';
	}
	$trace[] = ":";
	$trace[] = trim($action);
	$trace = "\n" . implode(" ", $trace);
	sql_update("spip_bank_recurrences", ['log' => "CONCAT(log, " . sql_quote($trace).")"], 'id_bank_recurrence='.intval($id_bank_recurrence));
}
