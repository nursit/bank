<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('base/abstract_sql');

/**
 * Generer un ticket resume de la transaction
 * pour les admins indiques dans la configuration
 * 
 * @param <type> $id_transaction
 * @return <type>
 */
function inc_bank_editer_ticket_admin_dist($id_transaction){
	// il faut avoir configure un ou des emails de notification
	$c = unserialize($GLOBALS['meta']['bank_paiement']);
	if (!strlen($email = $c['email_ticket_admin'])){
		spip_log(var_export($GLOBALS['meta']['bank_paiement'],true),'bank_ticket');
		return;
	}
	$ticket = "";

	$res = sql_select("*","spip_transactions","id_transaction=".intval($id_transaction));
	if ($row = sql_fetch($res)){
		$montant = $row['montant_regle'];
		$ticket .= "<h2>Transaction $id_transaction</h2>\n<table border='1'>";
		foreach($row as $k=>$v)
			$ticket .= "<tr><td>$k</td><td>$v</td></tr>";
		$ticket .="</table>";
	}

	// ensuite un pipeline pour editer le ticket
	$ticket = pipeline('bank_editer_ticket_reglement',array(
		'args'=>array('id_transaction'=>$id_transaction),
		'data'=>$ticket)
	);


	$ticket = "<html>$ticket</html>";
	$header = "MIME-Version: 1.0\n".
		"Content-Type: text/html; charset=".$GLOBALS['meta']['charset']."\n".
		"Content-Transfer-Encoding: 8bit\n";
	$sujet = "Transaction OK #$id_transaction [".affiche_monnaie($montant)."]";
	$url = parse_url($GLOBALS['meta']['adresse_site']);

	$envoyer_mail = charger_fonction('envoyer_mail','inc');
	$envoyer_mail($email,$sujet,$ticket,'reglements@'.$url['host'],$header);
}

?>
