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

function genie_bank_daily_reporting_dist($t){

	// entre minuit et 7h du matin
	$now = time();
	if (intval(date('H', $now))>=1 AND intval(date('H', $now))<=7){
		// il faut avoir configure un ou des emails de notification
		if (!isset($GLOBALS['meta']['bank_paiement'])
		  or !$c = unserialize($GLOBALS['meta']['bank_paiement'])) {
			$c = array();
		}
		if (isset($c['email_reporting']) AND strlen($email = $c['email_reporting'])){
			include_spip('inc/filtres');

			$texte = "";
			// le nombre et le montant des transactions par jour sur les 15 derniers jours
			$j = date('Y-m-d 00:00:00', $now);
			$jm15 = date('Y-m-d 00:00:00', strtotime("-15 day", $now));


			$jours = sql_allfetsel(
				'date_paiement,sum(montant_ht) as total_ht,sum(montant) as total,count(id_transaction) as nb',
				'spip_transactions',
				'statut=' . sql_quote('ok') . ' AND date_paiement>=' . sql_quote($jm15) . ' AND date_paiement<' . sql_quote($j),
				'DAYOFMONTH(date_paiement)', 'date_paiement DESC'
			);
			$lignes = "";
			foreach ($jours as $jour){
				if ($jour['date_paiement']>date('Y-m-d 00:00:00', strtotime("-1 day", $now))){
					$texte .= "<h2>" . affdate($jour['date_paiement']) . "</h2>
" . $jour['nb'] . " Paiements OK <br />
Total HT : <b>" . affiche_monnaie($jour['total_ht']) . "</b><br />
Total TTC : <b>" . affiche_monnaie($jour['total']) . "</b><br />
";
				}
				$lignes .= "<tr><td>" . affdate($jour['date_paiement']) . "</td><td>" . $jour['nb'] . "</td><td>" . affiche_monnaie($jour['total_ht']) . "</td><td>" . affiche_monnaie($jour['total']) . "</td></tr>\n";
			}
			$texte .= "<h2>Paiements 15 derniers jours</h2>
<table class='spip'>
<tr><th>Jour</th><th>Nb</th><th>Total HT</th><th>Total TTC</th></tr>
$lignes
</table>";

			$jm365 = date('Y-m-01 00:00:00', strtotime("-13 month", $now));
			$mois = sql_allfetsel(
				'date_paiement,sum(montant_ht) as total_ht,sum(montant) as total,count(id_transaction) as nb',
				'spip_transactions',
				'statut=' . sql_quote('ok') . ' AND date_paiement>=' . sql_quote($jm365),
				"DATE_FORMAT(date_paiement,'%Y-%m')", 'date_paiement DESC'
			);
			$lignes = "";
			foreach ($mois as $moi){
				$lignes .= "<tr><td>" . affdate_mois_annee($moi['date_paiement']) . "</td><td>" . $moi['nb'] . "</td><td>" . affiche_monnaie($moi['total_ht']) . "</td><td>" . affiche_monnaie($moi['total']) . "</td></tr>\n";
			}

			$texte .= "<h2>Paiements 12 derniers mois</h2>
<table class='spip'>
<tr><th>Mois</th><th>Nb</th><th>Total HT</th><th>Total TTC</th></tr>
$lignes
</table>";

			$texte = "<html>$texte</html>";
			$header = "MIME-Version: 1.0\n" .
				"Content-Type: text/html; charset=" . $GLOBALS['meta']['charset'] . "\n" .
				"Content-Transfer-Encoding: 8bit\n";
			$sujet = "[" . $GLOBALS['meta']['nom_site'] . "] Reporting Paiements";

			$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
			$envoyer_mail($email, $sujet, $texte, '', $header);

			spip_log("Envoi reporting quotidien", 'bank');
		}
	}

	// une alerte mail sur les transactions dont le traitement du paiement a ete interrompu
	if ($transactions = sql_allfetsel('*', 'spip_transactions', 'finie<0')){
		include_spip('inc/filtres');
		if (!isset($GLOBALS['meta']['bank_paiement'])
		  or !$c = unserialize($GLOBALS['meta']['bank_paiement'])) {
			$c = array();
		}
		$email = $GLOBALS['meta']['email_webmaster'];
		if (isset($c['email_ticket_admin'])){
			$email = $c['email_ticket_admin'];
		}


		$tableau = "";
		foreach ($transactions as $transaction){
			$ligne = "";
			foreach (array('id_transaction', 'id_auteur', 'auteur', 'date_transaction', 'montant', 'mode', 'statut', 'id_commande', 'id_facture', 'finie') as $k){
				$ligne .= "<td>" . $transaction[$k] . "</td>";
			}
			$tableau .= "<tr>$ligne</tr>\n";
		}

		$tableau = "<table class='spip'>
<thead>
<tr><td>#</td><td>id_auteur</td><td>auteur</td><td>date</td><td>montant</td><td>mode</td><td>statut</td><td>id_commande</td><td>id_facture</td><td>finie</td></tr>
</thead>
<tbody>
$tableau
</tbody>
</table>";

		$nb = count($transactions);
		$nb = singulier_ou_pluriel($nb, 'bank:info_1_transaction', 'bank:info_nb_transactions');
		$titre = "$nb en ECHEC";

		$texte = "<html><h1>$titre</h1>\n$tableau</html>";
		$header = "MIME-Version: 1.0\n" .
			"Content-Type: text/html; charset=" . $GLOBALS['meta']['charset'] . "\n" .
			"Content-Transfer-Encoding: 8bit\n";
		$sujet = "[" . $GLOBALS['meta']['nom_site'] . "][ERREUR] $titre";

		$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
		$envoyer_mail($email, $sujet, $texte, '', $header);

	}


	return 1;
}