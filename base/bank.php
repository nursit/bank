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
if (!defined("_ECRIRE_INC_VERSION")){
	return;
}

function bank_declarer_tables_interfaces($interface){
	// 'spip_' dans l'index de $tables_principales
	$interface['table_des_tables']['transactions'] = 'transactions';

	return $interface;
}

function bank_declarer_tables_objets_sql($tables){
	$tables['spip_transactions'] = array(
		'page' => false,
		'titre' => "id_transaction as titre, '' as lang",
		'date' => 'date_transaction',
		'principale' => 'oui',
		'texte_objets' => 'bank:titre_transactions',
		'texte_objet' => 'bank:titre_transaction',
		'info_aucun_objet' => 'bank:info_aucune_transaction',
		'info_1_objet' => 'bank:info_1_transaction',
		'info_nb_objets' => 'bank:info_nb_transactions',
		'field' => array(
			"id_transaction" => "bigint(21) NOT NULL",
			"id_auteur" => "bigint(21) NOT NULL", // auteur (spip) loge qui realise la transaction
			"auteur_id" => "varchar(255) NOT NULL DEFAULT ''", // autre identifiant auteur a defaut
			"auteur" => "varchar(255) NOT NULL DEFAULT ''", // a defaut info auteur autre (email, n°...)
			"transaction_hash" => "bigint(21) NOT NULL DEFAULT 0", // signature qui sert a securise un minima les skels
			"date_transaction" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",

			"contenu" => "TEXT NOT NULL DEFAULT ''", // contenu sous forme texte serializee, a toute fin utile
			"montant_ht" => "varchar(25) NOT NULL DEFAULT ''", // montant ht en euros
			"montant" => "varchar(25) NOT NULL DEFAULT ''", // montant ttc en euros
			"devise" => "char(3) NOT NULL DEFAULT 'EUR'", // code alpha d'une devise, normalement en majuscule

			"mode" => "varchar(25) NOT NULL DEFAULT ''", // mode de paiement (prestataire)
			"autorisation_id" => "varchar(255) NOT NULL DEFAULT ''", // numero d'autorisation de debit envoye par le presta bancaire
			"refcb" => "varchar(100) NOT NULL DEFAULT ''", // reference CB partielle (type/numero de carte tronque par exemple)
			"validite" => "varchar(10) NOT NULL DEFAULT ''", // fin de validite de la CB (pour les paiements recurrents)
			"abo_uid" => "varchar(100) NOT NULL DEFAULT ''", // numero d'abonne pour les paiements recurrents
			"pay_id" => "varchar(100) NOT NULL DEFAULT ''", // identifiant pour declencher un nouveau paiement sans saisie de carte

			"montant_regle" => "varchar(25) NOT NULL DEFAULT ''", // montant regle (renvoye par le presta) en euros
			"date_paiement" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
			"statut" => "varchar(25) NOT NULL DEFAULT ''", // commande, ok (ok si est passee en reglement)
			"reglee" => "varchar(3) NOT NULL DEFAULT 'non'", // oui/non (non si reglement incomplet/partiel)
			"finie" => "tinyint(1) NOT NULL DEFAULT 0",

			"erreur" => "tinytext NOT NULL DEFAULT ''", // erreur en clair, pour l'admin
			"message" => "TEXT NOT NULL DEFAULT ''", // message de retour a afficher

			"parrain" => "varchar(255) NOT NULL DEFAULT ''", // sponsor de la transaction (apporteur affilie, url site source..)
			"tracking_id" => "bigint(21) NOT NULL DEFAULT 0", // numero de tracking affiliateur

			"id_panier" => "bigint(21) NOT NULL DEFAULT 0", // possibilite de referencer un panier ici, mais a gerer hors du plugin
			"id_commande" => "bigint(21) NOT NULL DEFAULT 0", // possibilite de referencer une commande ici, mais a gerer hors du plugin
			"id_facture" => "bigint(21) NOT NULL DEFAULT 0", // factures non gerees par ce plugin, mais champ prevu pour

			"cadeau_email" => "varchar(100) NOT NULL DEFAULT ''", // achat a l'attention d'une tierce personne
			"cadeau_message" => "TEXT NOT NULL DEFAULT ''",  // achat a l'attention d'une tierce personne

			"url_retour" => "text DEFAULT '' NOT NULL", // retour sur un site externe en fin de transaction
			"token" => "VARCHAR(25) DEFAULT '' NOT NULL",  // jeton perissable fourni au retour externe

			"maj" => "TIMESTAMP"
		),
		'key' => array(
			"PRIMARY KEY" => "id_transaction",
			"KEY id_auteur" => "id_auteur",

			"KEY id_panier" => "id_panier",
			"KEY id_facture" => "id_facture",
		),
		'rechercher_champs' => array(
			'id_transaction' => 1,
			'auteur_id' => 1,
			'auteur' => 1,
			'autorisation_id' => 1,
			'parrain' => 1,
			'tracking_id' => 1,
			'mode' => 1,
		),
		'statut_images' => [
			'commande' => 'puce-prepa-8.png',
			'attente' => 'puce-prop-8.png',
			'ok' => 'puce-publier-8.png',
			'rembourse' => 'puce-refuser-8.png',
			'echec' => 'puce-supprimer-8.png',
			'abandon' => 'puce-supprimer-8.png',
		],
	);

	$tables['spip_bank_recurrences'] = array(
		'page' => false,
		'titre' => "uid as titre, '' as lang",
		'date' => 'date_creation',
		'principale' => 'oui',
		'texte_objets' => 'bank:titre_bank_recurrences',
		'texte_objet' => 'bank:titre_bank_recurrence',
		'info_aucun_objet' => 'bank:info_aucune_bank_recurrence',
		'info_1_objet' => 'bank:info_1_bank_recurrence',
		'info_nb_objets' => 'bank:info_nb_bank_recurrences',
		'field' => array(
			"id_bank_recurrence" => "bigint(21) NOT NULL",
			"id_transaction" => "bigint(21) NOT NULL DEFAULT 0", // id_transaction de la premiere occurence
			"date_creation" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
			"uid" => "varchar(55) NOT NULL DEFAULT ''", // numero de recurence unique

			"echeances" => "TEXT NOT NULL DEFAULT ''", // contenu sous forme json de la description des echeances
			"date_start" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL", // date de la premiere echeance

			"date_echeance" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL", // date de la derniere echeance payee
			"count_echeance" => "bigint(21) NOT NULL DEFAULT 0", // compteur de la derniere echeance payee
			"id_transaction_echeance" => "bigint(21) NOT NULL DEFAULT 0", // id_transaction de la derniere echeance payee

			"date_echeance_next" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL", // date de l'echeance suivante
			"id_transaction_echeance_next" => "bigint(21) NOT NULL DEFAULT 0", // id_transaction de la prochaine echeance / sert de jeton aussi pour eviter un double traitement en parallèle
			"date_fin_prevue" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL", // date de fin cause validite carte par exemple, ou cause nombre d'echeances atteint

			"payment_data" => "TEXT NOT NULL DEFAULT ''", // informations nécessaires pour déclencher un nouveau paiement

			"statut" => "varchar(25) NOT NULL DEFAULT ''", // prepa,valide,echec,fini
			"date_fin" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL", // date de fin cause validite carte par exemple, ou cause nombre d'echeances atteint

			"maj" => "TIMESTAMP"
		),
		'key' => array(
			"PRIMARY KEY" => "id_bank_recurrence",
			"UNIQUE id_transaction" => "id_transaction",
		),
		'rechercher_champs' => array(
			'id_bank_recurrence' => 1,
			'uid' => 1,
		),
		'statut' => [
			[
				'champ' => 'statut',
				'publie' => 'valide',
				'previsu' => 'prepa,valide,echec,fini',
				'exception' => ['statut', 'tout']
			]
		],
		'statut_titres' => [
			'prepa' => 'bank:info_recurrence_statut_prepa',
			'valide' => 'bank:info_recurrence_statut_valide',
			'echec' => 'bank:info_recurrence_statut_echec',
			'fini' => 'bank:info_recurrence_statut_fini',
		],
		'statut_textes_instituer' => [
			'prepa' => 'bank:info_recurrence_statut_prepa',
			'valide' => 'bank:info_recurrence_statut_valide',
			'echec' => 'bank:info_recurrence_statut_echec',
			'fini' => 'bank:info_recurrence_statut_fini',
		],
		'statut_images' => [
			'prepa' => 'puce-prepa-8.png',
			'valide' => 'puce-publier-8.png',
			'echec' => 'puce-supprimer-8.png',
			'fini' => 'puce-refuser-8.png',
		],
		'texte_changer_statut' => 'bank:info_changer_statut_bank_recurrence',
	);
	return $tables;
}
