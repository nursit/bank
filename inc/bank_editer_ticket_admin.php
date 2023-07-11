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

include_spip('base/abstract_sql');

/**
 * Generer un ticket resume de la transaction
 * pour les admins indiques dans la configuration
 *
 * @param int $id_transaction
 * @param string $sujet
 */
function inc_bank_editer_ticket_admin_dist($id_transaction, $sujet = "Transaction OK"){
	// il faut avoir configure un ou des emails de notification
	if (!isset($GLOBALS['meta']['bank_paiement'])
	  or !$c = unserialize($GLOBALS['meta']['bank_paiement'])) {
		$c = array();
	}
	if (!isset($c['email_ticket_admin']) OR !strlen($email = $c['email_ticket_admin'])){
		if (!isset($GLOBALS['meta']['bank_paiement'])) {
			spip_log("Aucune configuration dans bank_paiement", 'bank_ticket' . _LOG_ERREUR);
		}
		else {
			spip_log("Configuration bank_paiement incomplete ".var_export($GLOBALS['meta']['bank_paiement'], true), 'bank_ticket' . _LOG_ERREUR);
		}
		return;
	}
	$ticket = "";

	if ($row = sql_fetsel("*", "spip_transactions", "id_transaction=" . intval($id_transaction))){

		include_spip('inc/bank');
		$description = bank_description_transaction($id_transaction, $row);
		$description_html = "<strong>" . $description['libelle'] . "</strong>" . "\n" . $description['description'];
		$description_html = nl2br($description_html);

		$montant = $row['montant_regle'];
		$ticket .= "<h2>Transaction $id_transaction</h2>\n<p>$description_html</p>\n<table>\n";
		foreach ($row as $k => $v) {
			$ticket .= "<tr><td class='first'><b>$k</b></td><td>$v</td></tr>\n";
		}

		if ($billing = bank_porteur_infos_facturation($row)) {
			$ticket .= "<tr><td colspan='2' bgcolor='#eee' style='background-color:#eee;padding:5px 1em;text-align:center;'><b>Facturation</b></td></tr>";
			foreach ($billing as $k => $v) {
				$ticket .= "<tr><td class='first'><b>$k</b></td><td>$v</td></tr>";
			}
		}

		$ticket .= "</table>";
	}

	// ensuite un pipeline pour editer le ticket
	$ticket = pipeline('bank_editer_ticket_reglement', array(
			'args' => array('id_transaction' => $id_transaction),
			'data' => $ticket)
	);

	$ticket = <<<html
<html>
<head>
<style type="text/css">
table {border: 4px solid #888;border-collapse: collapse;}
table td {padding: 2px 0.5em; border: 2px solid #ccc;}
table td.first {width:12em;}
</style>
</head>
<body>$ticket</body>
</html>
html;
	$header = "MIME-Version: 1.0\n" .
		"Content-Type: text/html; charset=" . $GLOBALS['meta']['charset'] . "\n" .
		"Content-Transfer-Encoding: 8bit\n";
	$sujet = "$sujet #$id_transaction [" . bank_affiche_montant($montant, $row['devise']) . "]";
	if (strlen($description['libelle'])){
		$sujet .= " | " . $description['libelle'];
	}

	if (!isset($c['email_from_ticket_admin']) OR !strlen($email_from = $c['email_from_ticket_admin'])){
		$url = parse_url($GLOBALS['meta']['adresse_site']);
		$email_from = "reglements@" . ltrim($url['host'], 'w.');
	}

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($email, $sujet, $ticket, $email_from, $header);
}
