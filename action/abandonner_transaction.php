<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2018 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function action_abandonner_transaction_dist($id_transaction=null){

	if (is_null($id_transaction)){
		$securiser_action = charger_fonction('securiser_action','inc');
		$id_transaction = $securiser_action();
	}

	if ($id_transaction=intval($id_transaction)
		AND $row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction))
	  AND $row["statut"]=="commande"){

		sql_updateq('spip_transactions',array('statut'=>'abandon'),"id_transaction=".intval($id_transaction));

		if ($row['id_panier']){
			if ($abandonner_panier = charger_fonction('abandonner_panier', 'action', true)){
				$abandonner_panier($row['id_panier']);
			}
			elseif ($supprimer_panier = charger_fonction('supprimer_panier', 'action', true)){
				$supprimer_panier($row['id_panier']);
			}
		}
		if ($row['id_commande']
		  AND $abandonner_commande = charger_fonction('abandonner_commande','action',true)) {
			$abandonner_commande($row['id_commande']);
		}
	}
}