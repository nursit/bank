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
if (!defined("_ECRIRE_INC_VERSION")) return;

function bank_declarer_tables_interfaces($interface){
	// 'spip_' dans l'index de $tables_principales
	$interface['table_des_tables']['transactions']='transactions';


	return $interface;
}

function bank_declarer_tables_principales($tables_principales){

	$spip_transactions = array(
		"id_transaction" 	=> "bigint(21) NOT NULL",
		"id_auteur" 	=> "bigint(21) NOT NULL", // auteur (spip) loge qui realise la transaction
		"auteur_id" 	=> "varchar(19) NOT NULL", // autre identifiant auteur a defaut
		"auteur" 	=> "varchar(255) NOT NULL", // a defaut info auteur autre (email, n°...)
		"transaction_hash" 	=> "bigint(21) NOT NULL DEFAULT 0", // signature qui sert a securise un minima les skels
		"date_transaction" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",

		"contenu" => "TEXT NOT NULL DEFAULT ''", // contenu sous forme texte serializee, a toute fin utile
		"montant_ht" 	=> "varchar(25) NOT NULL DEFAULT ''", // montant ht en euros
		"montant" 	=> "varchar(25) NOT NULL DEFAULT ''", // montant ttc en euros

		"mode" => "varchar(25) NOT NULL DEFAULT ''", // mode de paiement (prestataire)
		"autorisation_id" 	=> "varchar(55) NOT NULL DEFAULT ''", // numero d'autorisation de debit envoye par le presta bancaire
		"refcb" 	=> "varchar(100) NOT NULL DEFAULT ''", // reference CB partielle pour declencher un nouveau paiement
		"abo_uid" 	=> "varchar(55) NOT NULL DEFAULT ''", // numero d'abonne pour les paiements recurrents
		"validite" 	=> "varchar(10) NOT NULL DEFAULT ''", // fin de validite de la CB pour les paiements recurrents

		"montant_regle" 	=> "varchar(25) NOT NULL DEFAULT ''", // montant regle (renvoye par le presta) en euros
		"date_paiement" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
		"statut" => "varchar(25) NOT NULL DEFAULT ''", // commande, ok (ok si est passee en reglement)
		"reglee" => "varchar(3) NOT NULL DEFAULT 'non'", // oui/non (non si reglement incomplet/partiel)
		"finie" => "tinyint(1) NOT NULL DEFAULT 0",

		"message" => "TEXT NOT NULL DEFAULT ''", // message de retour a afficher

		"parrain" => "varchar(255) NOT NULL DEFAULT ''", // sponsor de la transaction (apporteur affilie, url site source..)
		"tracking_id" => "bigint(21) NOT NULL DEFAULT 0", // numero de tracking affiliateur

		"id_panier" 	=> "bigint(21) NOT NULL DEFAULT 0", // possibilite de referencer un panier ici, mais a gerer hors du plugin
		"id_commande" => "bigint(21) NOT NULL DEFAULT 0", // possibilite de referencer une commande ici, mais a gerer hors du plugin
		"id_facture" 	=> "bigint(21) NOT NULL DEFAULT 0", // factures non gerees par ce plugin, mais champ prevu pour

		"cadeau_email" 	=> "varchar(100) NOT NULL DEFAULT ''", // achat a l'attention d'une tierce personne
		"cadeau_message" 	=> "TEXT NOT NULL DEFAULT ''",  // achat a l'attention d'une tierce personne

		"url_retour"	=> "text DEFAULT '' NOT NULL", // retour sur un site externe en fin de transaction
		"token" => "VARCHAR(25) DEFAULT '' NOT NULL",  // jeton perissable fourni au retour externe
			
		"maj" 		=> "TIMESTAMP");

	$spip_transactions_key = array(
		"PRIMARY KEY" 	=> "id_transaction",
		"KEY id_auteur" => "id_auteur",

		"KEY id_panier" => "id_panier",
		"KEY id_facture" => "id_facture",
	);

	$tables_principales['spip_transactions'] = array(
		'field' => &$spip_transactions,
		'key' => &$spip_transactions_key);

	return $tables_principales;
}


function bank_rechercher_liste_des_champs($flux){

	$flux['transaction'] = array(
		'id_transaction' => 1,
		'auteur_id' => 1,
		'auteur' => 1,
		'autorisation_id' => 1,
		'parrain' => 1,
		'tracking_id' => 1,
		'mode' => 1,
	);
	return $flux;
}

?>