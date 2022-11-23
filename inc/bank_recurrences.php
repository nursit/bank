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
		'sujet' => 'Echec création recurrence',
		'update' => false,
		'where' => 'bank_recurrence_creer',
	);
	$args = array_merge($default, $args);
	$logname = str_replace(array('1', '2', '3', '4', '5', '6', '7', '8', '9'), array('un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'), $args['mode']);

	spip_log($t = $args['where'] . " : " . $args['sujet'] . " #$id_transaction (" . $args['erreur'] . ") " . $args['log'], $logname . _LOG_ERREUR);
	spip_log($t, $logname . "_invalides" . _LOG_ERREUR);

	if ($args['send_mail']) {
		// avertir le webmestre
		$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
		$envoyer_mail($GLOBALS['meta']['email_webmaster'], "[" . $args['mode'] . "] " . $args['sujet'], $t);
	}

	$args['update'] = false;
	return bank_transaction_invalide(empty($args['id_transaction']) ? 0 : $args['id_transaction'], $args);
}

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

function bank_recurrence_decode_uid($uid) {
	$hash = substr($uid, 4);
	$hash = hex2bin($hash);
	if ($d = gzinflate($hash)) {
		$hash = $d;
	}
	$parts = explode('.', $hash);
	$parts = array_map('hexdec',$parts);
	$id_bank_recurrence = array_shift($parts);
	$id_transaction = array_shift($parts);
	return [$id_bank_recurrence, $id_transaction];
}

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
	$uid = bank_recurrence_generate_uid($id_bank_recurrence, $id_transaction);
	$set = array(
		'uid' => $uid,
	);
	if (!sql_updateq('spip_bank_recurrences', $set, 'id_bank_recurrence=' . intval($id_bank_recurrence))) {
		return false;
	}

	return $uid;
}
