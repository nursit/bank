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


function bank_recurrence_creer($id_transaction) {
	$abo_uid = "";

	// dans tous les cas on créé la récurrence en base en mode prepa, sauf si elle existe déjà
	if (!$recurrence = sql_fetsel('*', 'spip_bank_recurrences', 'id_transaction='.intval($id_transaction))) {
		$ins = array(
			'id_transaction' => $id_transaction,
			'statut' => 'prepa',
			'date_creation' => date('Y-m-d H:i:s'),
		);
		$id_bank_recurrences = sql_insertq('spip_bank_recurrences', $ins);

	}
	if (!$decrire_echeance = charger_fonction("decrire_echeance", "abos", true)
		or !$echeance = $decrire_echeance($id_transaction)) {
		// ECHEC description echeance, on ne sait pas creer un abonnement

	}

	// TODO : creer la recurrence

	return $abo_uid;
}
