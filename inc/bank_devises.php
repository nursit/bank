<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * (c) 2012-2020 - Distribue sous licence GNU/GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

/**
 * Renvoie la liste des devises possibles, et toutes leurs infos nécessaires
 * en s'assurant que la liste fournie par les plugins est toujours conforme
 * et contient au moins la devise EUR
 *
 * @pipeline_appel bank_lister_devises
 * @return array
 * 		Tableau de toutes les devises avec pour chacune leurs infos complètes au format
 * 		```
 * 		array(
 * 			'EUR' => array(
 * 				'code' => 'EUR',
 * 				'code_num' => 978,
 * 				'nom' => 'euro',
 * 				'fraction' => 2,
 * 				'symbole' => '€',
 * 				'locale' => 'fr_FR',
 * 			)
 * 		)
 * 		```
 */
function bank_lister_devises() {
	static $devises = null;

	if (is_null($devises)) {
		$devises_dist = array(
			'EUR' => array(
				'code' => 'EUR',
				'code_num' => 978,
				'nom' => 'euro',
				'fraction' => 2,
				'symbole' => '€',
			),
		);

		$devises = pipeline('bank_lister_devises', $devises_dist);

		// s'assurer que la chaque devise est conforme et complete
		foreach ($devises as $k => $devise) {
			if (empty($devise['code'])
				or empty($devise['code_num'])
				or empty($devise['nom'])
				or !isset($devise['fraction'])
				or empty($devise['symbole'])
			) {
				unset($devises[$k]);
			}
		}

		if (empty($devises['EUR'])) {
			$devises = array_merge($devises, $devises_dist);
		}
	}

	return $devises;
}

/**
 * Renvoie une ou toutes les infos sur une devise
 *
 * @param string $code
 *    Identifiant ISO 4217 alpha d'une devise
 */
function bank_devise_info($code, $info='') {
	$retour = null;

	if ($code) {
		$code = strtoupper($code);
		$devises = bank_lister_devises();

		if (isset($devises[$code])) {
			$retour = $devises[$code];
		}
	}
	else {
		$retour = bank_devise_defaut();
	}

	// si une info est demandee, il faut retourner une valeur credible meme si la devise n'est plus connue
	// cas du traitement differe d'un paiement
	if ($info) {
		if ($retour and isset($retour[$info])) {
			$retour = $retour[$info];
		}
		else {
			switch ($info) {
				case 'fraction':
					$retour = 2; // best bet
				  break;
				case 'code':
				case 'code_num':
				case 'nom':
				case 'symbole':
					$retour = $code;
					break;
				default:
					$retour = null;
					break;
			}
		}
	}

	return $retour;
}

/**
 * Renvoie la devise par défaut utilisée par Bank, modifiable par pipeline
 *
 * @pipeline_appel bank_devise_defaut
 * @return array|null
 *   description de la devise par defaut
 */
function bank_devise_defaut() {
	static $devise_defaut = null;

	if (is_null($devise_defaut)) {
		$devise_defaut_code = pipeline('bank_devise_defaut', 'EUR');

		$devise_defaut_code = ($devise_defaut_code ? $devise_defaut_code : 'EUR');
		$devise_defaut = bank_devise_info($devise_defaut_code);

		if (!$devise_defaut) {
			// EUR est toujours connu et defini
			$devise_defaut = bank_devise_info('EUR');
		}
	}

	return $devise_defaut;
}

/**
 * Tester si une devise est supportée par un prestataire
 *
 * @param array $config
 * 		Tableau avec toutes les infos de config d'un prestataire
 * @param string $devise
 * 		Devise à tester en code ISO 4217 alphabétique
 * @return bool
 * 		Renvoie true si la devise est ok, false sinon
 *
 */
function bank_tester_devise_presta($config, $devise = null) {
	$ok = false;

	// Si pas de devise, on prend celle générale par défaut
	if (!$devise) {
		$devise_defaut = bank_devise_defaut();
		$devise = $devise_defaut['code'];
	}

	// Par défaut on accepte l'euro comme avant, ce qui évite d'implémenter partout
	$devises_ok = array('EUR');

	// Si le presta a une fonction qui définit les devises supportées, on l'utilise.
	// Elle retourne soit un tableau, soit un booléen pour les accepter toutes.
	if ($lister_devises = charger_fonction('lister_devises', 'presta/' . $config['presta'], true)) {
		$devises_ok = $lister_devises($config);
	}

	// Et enfin on teste
	if (is_array($devises_ok)) {
		// On normalise
		$devise = strtoupper($devise);
		$devises_ok = array_map('strtoupper', $devises_ok);
		$ok = in_array($devise, $devises_ok);
	}
	// true|false : principalement pour simu et gratuit qui acceptent toutes les devises par principe
	elseif (is_bool($devises_ok)) {
		$ok = $devises_ok;
	}

	return $ok;
}
