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

function presta_ogone_payer_acte_dist($id_transaction,$transaction_hash, $titre=''){

	include_spip('inc/bank');
	$config = bank_config("ogone");

	$call_request = charger_fonction('request','presta/ogone/call');
	$contexte = $call_request($id_transaction,$transaction_hash,$config);
	$contexte['title'] = $titre;

	$contexte['sandbox'] = (ogone_is_sandbox($config)?' ':'');

	$forms = recuperer_fond('presta/ogone/payer/acte',$contexte);
	$forms = ogone_form_sha_in($forms,$config);
	return $forms;
}

/**
 * signer le contexte du formulaire
 * s'applique sur le html pour permettre sa personalisation
 *
 * @param string $texte
 * @param array $config
 * @return string
 */
function ogone_form_sha_in($texte, $config=null) {
	// ne rien faire si pas de config
	if (!$config) return $texte;

	$forms = extraire_balises($texte,"form");

	foreach($forms as $form){
		$form_s = $form;
		$input = extraire_balises($form,"input");

		$args = array();
		foreach($input as $i){
			if (extraire_attribut($i, 'type')=='hidden') {
				$name = extraire_attribut($i, 'name');
				$value = extraire_attribut($i, 'value');
				// si jamais on applique 2 fois, supprimer la signature precedement calculee
				if ($name=="SHASign"){
					$form_s = str_replace($i,"",$form_s);
				}
				else {
					$args[$name] = $value;
				}
			}
		}

		$s = ogone_sha_in($args,$config);
		$form_s = str_replace(end($input),end($input)."<input type='hidden' name='SHASign' value='$s' />", $form_s);
		$texte = str_replace($form,$form_s,$texte);
	}

	return $texte;
}

?>