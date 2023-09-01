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
 * #PAYER_ACTE{sips,#ID_TRANSACTION,#HASH}
 * 4e argument optionnel : tableau d'options #ARRAY{title,'Payez!'}
 *
 * @param Object $p
 * @return Object
 */
function balise_PAYER_ACTE_dist($p){
	$_mode = interprete_argument_balise(1, $p);
	$_id = interprete_argument_balise(2, $p);
	$_hash = interprete_argument_balise(3, $p);
	$_opts = interprete_argument_balise(4, $p);

	$p->code = "";

	if ($_mode AND $_id AND $_hash){
		$p->code = "bank_affiche_payer($_mode,'acte',$_id,$_hash,$_opts)";
	}

	$p->interdire_scripts = false;
	return $p;
}


/**
 * #PAYER_ABONNEMENT{sips,#ID_TRANSACTION,#HASH}
 * 4e argument optionnel : tableau d'options #ARRAY{title,'Payez!'}
 *
 * @param Object $p
 * @return Object
 */
function balise_PAYER_ABONNEMENT_dist($p){
	$_mode = interprete_argument_balise(1, $p);
	$_id = interprete_argument_balise(2, $p);
	$_hash = interprete_argument_balise(3, $p);
	$_opts = interprete_argument_balise(4, $p);

	$p->code = "";

	if ($_mode AND $_id AND $_hash){
		$p->code = "bank_affiche_payer($_mode,'abo',$_id,$_hash,$_opts)";
	}

	$p->interdire_scripts = false;
	return $p;
}


/**
 * #GERER_ABONNEMENT{#MODE_PAIEMENT,#ABO_UID}
 * @param $p
 * @return mixed
 */
function balise_GERER_ABONNEMENT_dist($p){
	$_mode = interprete_argument_balise(1, $p);
	$_abo_uid = interprete_argument_balise(2, $p);
	$p->code = "";

	if ($_mode AND $_abo_uid){
		$p->code = "bank_affiche_gerer_abonnement($_mode,$_abo_uid)";
	}

	$p->interdire_scripts = false;
	return $p;
}


/**
 * Markup HTML pour le contenu des boutons qui peuvent afficher soit un logo, soit du texte
 *
 * @param $logo
 * @param $texte
 * @return string
 */
function bank_label_bouton_img_ou_texte($logo, $texte){
	if (!$logo){
		return $texte;
	}
	$balise_img = chercher_filtre('balise_img');
	$img = $balise_img($logo, $texte, 'logo');
	$img = inserer_attribut($img, 'title', $texte);
	return $img . " <span class='texte'>$texte</span>";
}

/**
 * Une fonction pour expliciter le mode de paiement en fonction du prestataire bancaire
 * par defaut c'est Carte Bancaire
 * sauf si une chaine de langue specifique existe
 * @param string $mode
 * @param int $id_transaction
 * @return string
 */
function bank_titre_type_paiement($mode, $id_transaction = 0){
	include_spip('inc/bank');
	$config = bank_config($mode);
	$presta = $config['presta'];

	// si le presta dispose d'une fonction specifique (pour faire difference entre CB et SEPA par exemple)
	if ($presta_titre_type_paiement = charger_fonction("titre_type_paiement", "presta/$presta", true)){
		$titre = $presta_titre_type_paiement($mode, $id_transaction);
		if ($titre){
			return $titre;
		}
	}

	// sinon chaine de langue specifique ou generique
	$titre = _T("bank:label_type_paiement_$presta", array('presta' => $presta), array('force' => false));
	if (!$titre){
		$titre = _T("bank:label_type_paiement_cb_generique", array('presta' => $presta));
	}
	return $titre;
}

/**
 * "Payer par Carte Bleue" ou autre nom de carte en clair en fonction du $code_carte interne a la banque
 * @param string $code_carte
 * @return string
 */
function bank_label_payer_par_carte($code_carte){
	$id = str_replace(" ", "_", strtoupper($code_carte));
	$carte = _T('bank:label_carte_' . $id, array(), array('force' => false));
	if (!$carte){
		#var_dump($code_carte);
		$carte = $code_carte;
	}
	return _T('bank:payer_par_carte', array('carte' => $carte));
}

/**
 * Explication facultative des modes de paiement, chaine de langue a fournir pour les afficher
 * @param string $mode
 * @return string
 */
function bank_explication_mode_paiement($mode, $abonnement = false) {
	$mode = preg_replace(",[/-]([0-9A-F]{4})$,Uims", "", $mode);
	$chaine = 'bank:explication_mode_paiement_' . $mode;
	if ($abonnement) {
		$chaine .= "_abonnement";
	}
	$explication = _T($chaine, array(), array('force' => false));
	return $explication;
}


/**
 * Afficher l'inclusion attente reglement si elle existe,
 * en fonction du presta
 * @param string $mode
 * @param int $id_transaction
 * @param string $transaction_hash
 * @return string
 */
function bank_afficher_attente_reglement($mode, $id_transaction, $transaction_hash, $type){
	include_spip('inc/bank');
	$config = bank_config($mode);
	$presta = $config['presta'];
	if (trouver_fond("attente", "presta/$presta/payer/")){
		return recuperer_fond("presta/$presta/payer/attente", array('id_transaction' => $id_transaction, 'transaction_hash' => $transaction_hash, 'config' => $config, 'type' => $type));
	}
	return "";
}

/**
 * Mise en forme du mode dans la liste des transactions
 * @param string $mode
 * @return string
 */
function bank_afficher_mode($mode){
	$mode = preg_replace(",[/-]([0-9A-F]{4})$,Uims", " <span class='small'>\\1</span>", $mode);
	return $mode;
}

/**
 * Urls d'auto response pour afficher dans la config de certains prestas
 * @param $mode
 * @return string
 */
function bank_url_autoresponse($config){
	include_spip('inc/bank');
	if (!isset($config['presta'])){
		return "";
	}
	return bank_url_api_retour($config, "autoresponse");
}

function filtre_bank_lister_configs_dist($type){
	include_spip('inc/bank');
	return bank_lister_configs($type);
}

function filtre_bank_trouver_logo_dist($mode, $logo) {
	include_spip('inc/bank');
	return bank_trouver_logo($mode, $logo);
}

/**
 * Afficher la liste des transactions d'un auteur sur la page auteur de l'espace prive
 *
 * @pipeline affiche_auteurs_interventions
 * @param array $flux Donnees du pipeline
 * @return array       Donnees du pipeline
 */
function bank_affiche_auteurs_interventions($flux){
	if ($id_auteur = intval($flux['args']['id_auteur'])){

		$flux['data'] .= '<!--bank-->' . recuperer_fond('prive/squelettes/inclure/liste-transactions', array(
				'id_auteur' => $id_auteur,
				'quoi' => 'auteur',
			));

		if (sql_countsel('spip_bank_recurrences')) {
			$id_transactions = sql_allfetsel('id_transaction', 'spip_transactions', 'id_auteur='.intval($id_auteur));
			if (!empty($id_transactions)) {
				$id_transactions = array_column($id_transactions, 'id_transaction');
				$id_bank_recurrences = sql_allfetsel('id_bank_recurrence', 'spip_bank_recurrences', sql_in('id_transaction', $id_transactions) . ' OR '. sql_in('id_transaction_echeance', $id_transactions));
				if (!empty($id_bank_recurrences)) {
					$id_bank_recurrences = array_column($id_bank_recurrences, 'id_bank_recurrence');
					$flux['data'] .= '<!--bank-->' . recuperer_fond('prive/squelettes/inclure/liste-bank_recurrences', array(
							'id_bank_recurrence' => $id_bank_recurrences,
							'quoi' => 'auteur',
						));
				}
			}
		}

	}
	return $flux;
}

function bank_affiche_parrain($id_transaction, $parrain, $tracking_id) {
	if (empty($parrain)) {
		return '';
	}
	if ($f = charger_filtre('bank_affiche_parrain_'.$parrain, '')
	  and $s = $f($id_transaction, $tracking_id)) {
		return $s;
	}
	return trim("$parrain<br />\n$tracking_id");
}

function bank_traduire_statut_transaction($statut) {
	$statut = explode("[", $statut);
	$statut_clair = array_shift($statut);
	$statut_clair = _T('bank:info_statut_'.$statut_clair);
	array_unshift($statut, $statut_clair);
	return implode("[", $statut);
}

function bank_traduire_statut_bank_recurrence($statut) {
	$statut = _T('bank:info_recurrence_statut_'.$statut);
	return $statut;
}


function bank_payer_css($dummy = null) {
	$fichier_css = find_in_path("css/bank_payer.css");
	$css = file_get_contents($fichier_css);
	$css = urls_absolues_css($css, $fichier_css);
	return $css;
}
/**
 * Declarer les CRON
 *
 * @param array $taches_generales
 * @return array
 */
function bank_taches_generales_cron($taches_generales){
	// on fait tourner ce cron dans tous le cas car il surveille aussi
	// les eventuelles transactions interrompues en cours de traitement
	$taches_generales['bank_daily_reporting'] = 3600*6; // toutes les 6H

	// si on a un cron systeme qui lance spip bank:recurrences:watch c'est plus fiable et dans ce cas il faut inhiber le genie SPIP
	// avec un define('_BANK_INHIB_GENIE_RECURRENCES_WATCH', true);
	if ((!defined('_BANK_INHIB_GENIE_RECURRENCES_WATCH') or !_BANK_INHIB_GENIE_RECURRENCES_WATCH)
		and sql_countsel('spip_bank_recurrences', "statut='valide'")) {
		// toutes les 3H pour pas risque de rater des renouvellements, mais en principe tout se fait sur les appels en tout debut de journée
		$taches_generales['bank_recurrences_watch'] = 3600*3;
	}

	return $taches_generales;
}