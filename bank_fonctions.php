<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2015 - Distribue sous licence GNU/GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * #PAYER_ACTE{sips,#ID_TRANSACTION,#HASH}
 * 4e argument optionnel : tableau d'options #ARRAY{title,'Payez!'}
 *
 * @param Object $p
 * @return Object
 */
function balise_PAYER_ACTE_dist($p){
	$_mode = interprete_argument_balise(1,$p);
	$_id = interprete_argument_balise(2,$p);
	$_hash = interprete_argument_balise(3,$p);
	$_opts = interprete_argument_balise(4,$p);

	$p->code = "";

	if ($_mode AND $_id AND $_hash)
		$p->code = "bank_affiche_payer($_mode,'acte',$_id,$_hash,$_opts)";

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
	$_mode = interprete_argument_balise(1,$p);
	$_id = interprete_argument_balise(2,$p);
	$_hash = interprete_argument_balise(3,$p);
	$_opts = interprete_argument_balise(4,$p);

	$p->code = "";

	if ($_mode AND $_id AND $_hash)
		$p->code = "bank_affiche_payer($_mode,'abo',$_id,$_hash,$_opts)";

	$p->interdire_scripts = false;
	return $p;
}


/**
 * #GERER_ABONNEMENT{#MODE_PAIEMENT,#ABO_UID}
 * @param $p
 * @return mixed
 */
function balise_GERER_ABONNEMENT_dist($p){
	$_mode = interprete_argument_balise(1,$p);
	$_abo_uid = interprete_argument_balise(2,$p);
	$p->code = "";

	if ($_mode AND $_abo_uid)
		$p->code = "bank_affiche_gerer_abonnement($_mode,$_abo_uid)";

	$p->interdire_scripts = false;
	return $p;
}

/**
 * Une fonction pour expliciter le mode de paiement en fonction du prestataire bancaire
 * par defaut c'est Carte Bancaire
 * sauf si une chaine de langue specifique existe
 * @param string $mode
 * @param int $id_transaction
 * @return string
 */
function bank_titre_type_paiement($mode, $id_transaction=0){
	include_spip('inc/bank');
	$config = bank_config($mode);
	$presta = $config['presta'];

	// si le presta dispose d'une fonction specifique (pour faire difference entre CB et SEPA par exemple)
	if ($presta_titre_type_paiement = charger_fonction("titre_type_paiement","presta/$presta",true)){
		$titre =  $presta_titre_type_paiement($mode, $id_transaction);
		if ($titre) return $titre;
	}

	// sinon chaine de langue specifique ou generique
	$titre = _T("bank:label_type_paiement_$presta",array('presta'=>$presta),array('force'=>false));
	if (!$titre)
		$titre = _T("bank:label_type_paiement_cb_generique",array('presta'=>$presta));
	return $titre;
}

/**
 * "Payer par Carte Bleue" ou autre nom de carte en clair en fonction du $code_carte interne a la banque
 * @param string $code_carte
 * @return string
 */
function bank_label_payer_par_carte($code_carte){
	$carte = _T('bank:label_carte_'.$code_carte,array(),array('force'=>false));
	if (!$carte){
		#var_dump($code_carte);
		$carte = $code_carte;
	}
	return _T('bank:payer_par_carte',array('carte'=>$carte));
}

/**
 * Afficher l'inclusion attente reglement si elle existe,
 * en fonction du presta
 * @param string $mode
 * @param int $id_transaction
 * @param string $transaction_hash
 * @return string
 */
function bank_afficher_attente_reglement($mode,$id_transaction,$transaction_hash,$type){
	include_spip('inc/bank');
	$config = bank_config($mode);
	$presta = $config['presta'];
	if (trouver_fond("attente","presta/$presta/payer/")){
		return recuperer_fond("presta/$presta/payer/attente",array('id_transaction'=>$id_transaction,'transaction_hash'=>$transaction_hash,'config'=>$config,'type'=>$type));
	}
	return "";
}

/**
 * Mise en forme du mode dans la liste des transactions
 * @param string $mode
 * @return string
 */
function bank_afficher_mode($mode){
	$mode = preg_replace(",[/-]([0-9A-F]{4})$,Uims"," <span class='small'>\\1</span>",$mode);
	return $mode;
}

/**
 * Urls d'auto response pour afficher dans la config de certains prestas
 * @param $mode
 * @return string
 */
function bank_url_autoresponse($config){
	include_spip('inc/bank');
	if (!isset($config['presta'])) return "";
	return bank_url_api_retour($config,"autoresponse");
}

function filtre_bank_lister_configs_dist($type){
	include_spip('inc/bank');
	return bank_lister_configs($type);
}


/**
 * Afficher la liste des transactions d'un auteur sur la page auteur de l'espace prive
 *
 * @pipeline affiche_auteurs_interventions
 * @param  array $flux Donnees du pipeline
 * @return array       Donnees du pipeline
 */
function bank_affiche_auteurs_interventions($flux) {
	if ($id_auteur = intval($flux['args']['id_auteur'])) {

		$flux['data'] .= recuperer_fond('prive/objets/liste/transactions', array(
			'id_auteur' => $id_auteur,
		), array('ajax' => true));

	}
	return $flux;
}

/**
 * Declarer les CRON
 *
 * @param array $taches_generales
 * @return array
 */
function bank_taches_generales_cron($taches_generales){
	$c = unserialize($GLOBALS['meta']['bank_paiement']);
	if (isset($c['email_reporting']) AND strlen($email = $c['email_reporting'])){
		$taches_generales['bank_daily_reporting'] = 3600*6; // toutes les 6H
	}
	return $taches_generales;
}
