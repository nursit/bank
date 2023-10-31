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


// securite : on initialise une globale le temps de la config des prestas
if (isset($GLOBALS['meta']['bank_paiement'])
	AND $GLOBALS['config_bank_paiement'] = unserialize($GLOBALS['meta']['bank_paiement'])){

	foreach ($GLOBALS['config_bank_paiement'] as $nom => $config){
		if (strncmp($nom, "config_", 7)==0
			AND isset($config['actif'])
			AND $config['actif']
			AND isset($config['presta'])
			AND $presta = $config['presta']){
			// inclure le fichier config du presta correspondant
			include_spip("presta/$presta/config");
		}
	}

	// securite : on ne conserve pas la globale en memoire car elle contient des donnees sensibles
	unset($GLOBALS['config_bank_paiement']);
}

/**
 * fonction pour afficher facilement un montant en passant juste montant+devise
 *
 * @param string|float $montant
 * @param string $code_devise
 * @param bool|string $unite
 *   une valeur false signifie qu'on veut juste le montant bien formaté en nombre, sans devise
 * @param bool $raw
 *   une valeur true signifie qu'on veut le resultat sous forme de texte brut, sans markup
 * @return string
 */
function bank_affiche_montant($montant, $code_devise = null, $unite = true, $raw = false) {

	if (!function_exists('bank_devise_info')) {
		include_spip('inc/bank');
	}
	$devise = bank_devise_info($code_devise);

	include_spip('inc/filtres');
	if ($montant_formater = chercher_filtre('montant_formater')) {
		$options = [
			'currency' => $devise['code'],
			'currency_display' => $unite ? ($unite === 'symbol' ? 'symbol' : 'code') : 'none',
		];
		// si option $raw on veut un montant en texte brut, sans markup encapsulant
		if ($raw) {
			$options['markup'] = false;
		}
		$result = $montant_formater($montant, $options);
		// si jamais la fonction $montant_formater n'a pas respecte l'option markup, on nettoie pour faire au mieux
		// mais sans garantie du resultat...
		if ($raw) {
			$result = strip_tags($result);
		}
		return $result;
	}

	// falback : la veille fonction affiche_monnaie
	return affiche_monnaie($montant, $devise['fraction'], $unite ? ($raw ? ' ' : '&nbsp;') . $devise['code'] : '');
}


if (!function_exists('affiche_monnaie')){
	/**
	 * @param string | float $valeur
	 * @param int $decimales
	 * @param bool $unite
	 * @return string
	 * @deprecated
	 */
	function affiche_monnaie($valeur, $decimales = 2, $unite = true){
		if ($unite===true){
			if (!function_exists('bank_devise_defaut')) {
				include_spip('inc/bank');
			}
			$devise_defaut = bank_devise_defaut();
			$unite = '&nbsp;'.$devise_defaut['code'];
		}
		if (substr(trim($valeur), -1)=="%"){
			$unite = "&nbsp;%";
		}
		if (!$unite){
			$unite = "";
		}
		return sprintf("%.{$decimales}f", $valeur) . $unite;
	}
}

/**
 * Fonction appelee par la balise #PAYER_ACTE et #PAYER_ABONNEMENT
 * @param array|string $config
 *   string dans le cas "gratuit" => on va chercher la config via bank_config()
 * @param string $type
 * @param int $id_transaction
 * @param string $transaction_hash
 * @param array|string|null $options
 * @return string
 */
function bank_affiche_payer($config, $type, $id_transaction, $transaction_hash, $options = null){
	// compatibilite ancienne syntaxe, titre en 4e argument de #PAYER_XXX
	if (is_string($options)){
		$options = array(
			'payer_par_title' => $options,
		);
	}
	// invalide ou null ?
	if (!is_array($options)){
		$options = array();
	}

	// $config de type string ?
	include_spip('inc/bank');
	if (is_string($config)){
		$config = bank_config($config, $type=='abo');
	}

	$quoi = ($type=='abo' ? 'abonnement' : 'acte');

	// On va chercher la devise de la transaction
	if ($devise = sql_getfetsel('devise', 'spip_transactions', 'id_transaction = '.intval($id_transaction))) {
		$devise_info = bank_devise_info($devise);
		if (!$devise_info) {
			spip_log("bank_affiche_payer: Transaction #$id_transaction : la devise $devise n’est pas connue", 'bank' . _LOG_ERREUR);
			return '';
		}
	}
	// Sinon celle par défaut
	else {
		$devise_info = bank_devise_defaut();
	}

	// On teste si ce prestataire sait gérer la devise demandée, sinon on ne l'affiche pas
	if (!bank_tester_devise_presta($config, $devise_info['code'])) {
		spip_log("bank_affiche_payer: Transaction #$id_transaction la devise " . $devise_info['code'] . 'n’est pas supportée pour presta=' . $config['presta'], 'bank' . _LOG_ERREUR);
		return '';
	}

	if (!$payer = charger_fonction($quoi, 'presta/' . $config['presta'] . '/payer', true)) {
		spip_log("bank_affiche_payer: Transaction #$id_transaction pas de payer/$quoi pour presta=" . $config['presta'], "bank" . _LOG_ERREUR);
		return '';
	}

	include_spip('bank_fonctions');
	return $payer($config, $id_transaction, $transaction_hash, $options);
}

/**
 * Afficher le bouton pour gerer/interrompre un abonnement
 * @param array|string $config
 * @param string $abo_uid
 * @return array|string
 */
function bank_affiche_gerer_abonnement($config, $abo_uid){
	// $config de type string ?
	include_spip('inc/bank');
	if (is_string($config)){
		$config = bank_config($config, true);
	}

	if ($trans = sql_fetsel("*", "spip_transactions", $w = "abo_uid=" . sql_quote($abo_uid) . ' AND mode LIKE ' . sql_quote($config['presta'] . '%') . " AND " . sql_in('statut', array('ok', 'attente')), '', 'id_transaction')){
		$config = bank_config($trans['mode']);
		$fond = "modeles/gerer_abonnement";
		if (trouver_fond($f = "presta/" . $config['presta'] . "/payer/gerer_abonnement")){
			$fond = $f;
		}
		return recuperer_fond($fond, array('presta' => $config['presta'], 'id_transaction' => $trans['id_transaction'], 'abo_uid' => $abo_uid));
	}

	return "";
}
