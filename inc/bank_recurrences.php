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
 *
 * // TODO : calculer depuis la date de départ + n échéances plutot que depuis la date de dernière échéance pour éviter une dérive temporelle
 *
 * @param $echeances
 * @param $date_start
 * @param $date_echeance
 * @param $count_echeance
 * @param $date_fin
 * @return array|false
 */
function bank_recurrence_calculer_echeance_next($echeances, $date_start, $date_echeance, $count_echeance, $date_fin) {
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
	switch ($freq) {
		case 'daily':
			$date_next_echeance = date('Y-m-d H:i:s', strtotime('+1day', $t_last_echeance));
			break;
		case 'monthly':
			// cas particulier : c'est +1 mois mais en restant dans le mois suivant et en restant sur le jour anniversaire du start
			$d = date('d', strtotime($date_start));
			$this_month = strtotime(date('Y-m-01 H:i:s', $t_last_echeance));
			$next_month = date('Y-m', strtotime('+1month', $this_month));
			$date_next_echeance = $next_month . "-{$d} " . date('H:i:s', $t_last_echeance);
			$date_next_echeance = date('Y-m-d H:i:s', strtotime($date_next_echeance));
			while (strpos($date_next_echeance, $next_month) !== 0) {
				$date_next_echeance = strtotime('-1day',strtotime($date_next_echeance));
				$date_next_echeance = date('Y-m-d H:i:s', $date_next_echeance);
			}
			break;
		case 'yearly':
			$date_next_echeance = date('Y-m-d H:i:s', strtotime('+1year', $t_last_echeance));
			break;
	}
	$set = array(
		'date_echeance_next' => $date_next_echeance,
	);
	// si jamais on a atteint le nombre maxi d'echeances, alors la date theorique de la prochaine c'est la date de fin
	if (!empty($echeances['count'])) {
		$nb_max_echeances = $echeances['count'];
		if (!empty($echeances['count_init'])) {
			$nb_max_echeances += $echeances['count_init'];
		}
		if ($count_echeance >= $nb_max_echeances) {
			if (!intval($date_fin) or strtotime($date_fin) > strtotime($date_next_echeance)) {
				$set['date_fin'] = $date_next_echeance;
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
		$recurrence = sql_fetsel('*', 'spip_bank_recurrences', 'id_bank_recurrence=' . intval($id_bank_recurrence));
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
 * @return bool|mixed
 */
function bank_recurrence_activer($id_transaction, $abo_uid, $mode) {
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
	$now = time();
	$set = [
		'count_echeance' => 1,
		'date_start' => date('Y-m-d H:i:s', $now),
		'date_echeance' => date('Y-m-d H:i:s', $now),
		'id_transaction_echeance' => $id_transaction,
	];
	$validite = null;
	$date_fin = $recurrence['date_fin'];
	if (!empty($transaction['validite'])) {
		$validite = $transaction['validite'];
		// placer la date de fin au 01 du mois qui suit le mois de fin de validite (car on peut faire un paiement jusqu'au dernier jour du mois, 23h59)
		$date_fin_validite = strtotime('+4days',strtotime($validite . "-28"));
		$date_fin_validite = date('Y-m-01 00:00:00', $date_fin_validite);
		if (!intval($date_fin) or $date_fin_validite < $date_fin) {
			$date_fin = $date_fin_validite;
			$set['date_fin'] = $date_fin;
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
 * @return false|string
 */
function bank_recurrence_prolonger($id_transaction, $abo_uid, $mode) {
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

	$set_echeance = bank_recurrence_calculer_echeance_next(
		$recurrence['echeances'],
		$set['date_start'],
		$set['date_echeance'],
		$set['count_echeance'],
		$recurrence['date_fin']);


	if (!$set_echeance) {
		spip_log("bank_recurrence_renouveler: Renouvellement abonnement $abo_uid impossible (paiement transaction #$id_transaction) : impossible de calculer la prochaine echeance", $mode . _LOG_ERREUR);
		return false;
	}
	$set = array_merge($set, $set_echeance);

	sql_updateq('spip_bank_recurrences', $set, 'id_bank_recurrence='.intval($id_bank_recurrence));

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
				'count_echeance' => $recurrence['count_echeance'] + 1,
				'date_echeance' => date('Y-m-d H:i:s', $now),
				'id_transaction_echeance' => $id_transaction,
				'statut' => $statut
			);
			sql_updateq('spip_bank_recurrences', $set, 'id_bank_recurrence='.intval($id_bank_recurrence));
			spip_log("bank_recurrence_resilier: Résiliation abonnement $abo_uid statut : ".$recurrence['statut'] . " => $statut", $mode . _LOG_INFO_IMPORTANTE);
		}
		else {
			spip_log("bank_recurrence_resilier: Résiliation abonnement $abo_uid impossible car statut=".$recurrence['statut'], $mode . _LOG_ERREUR);
			$ok = false;
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
