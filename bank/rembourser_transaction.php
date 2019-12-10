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

/**
 * Enregistrer le reglement effectif d'une transaction
 * On peut passer ici 2 fois pour une meme transaction :
 * - lors de la notification serveur a serveur
 * - lors du retour de l'internaute par redirection depuis le presta bancaire
 *
 * @param int $id_transaction
 * @param array $options
 *   string message
 *   array row_prec
 *   bool notifier
 * @return bool
 */
function bank_rembourser_transaction_dist($id_transaction, $options = array()){

	$message = (isset($options['message']) ? $options['message'] : "");
	$notifier = (isset($options['notifier']) ? $options['notifier'] : true);

	if (!isset($options['row_prec'])){
		$options['row_prec'] = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction));
	}
	$row_prec = $options['row_prec'];

	// ne pas jouer 2 fois le traitement du remboursement
	if (!$row_prec OR $row_prec['statut']!=='ok'){
		return false;
	}

	// et on le pose aussitot
	sql_updateq('spip_transactions', array('statut' => 'rembourse'), "id_transaction=" . intval($id_transaction));

	$notifier = ($notifier AND $row_prec['statut']!='rembourse');

	$message = trim($row_prec['message'] . "\n" . trim($message));
	// ensuite un pipeline de traitement, notification etc...
	$message = pipeline('bank_traiter_remboursement', array(
			'args' => array(
				'id_transaction' => $id_transaction,
				'notifier' => $notifier,
				'avant' => $row_prec,
				'options' => $options,
			),
			'data' => $message)
	);

	sql_updateq("spip_transactions", array('message' => $message, 'finie' => 1), "id_transaction=" . intval($id_transaction));

	// notifier aux admins avec un ticket caisse
	if ($notifier){
		$bank_editer_ticket_admin = charger_fonction('bank_editer_ticket_admin', 'inc');
		$bank_editer_ticket_admin($id_transaction, "REMBOURSEMENT Transaction");
	}

	return true;
}
