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

	$call_request = charger_fonction('request','presta/ogone/call');
	$contexte = $call_request($id_transaction,$transaction_hash);
	$contexte['title'] = $titre;

	$contexte['sandbox'] = (_OGONE_TEST?' ':'');

	return recuperer_fond('presta/ogone/payer/acte',$contexte);
}

//
/**
 * signer le contexte du formulaire
 * s'applique sur le html pour permettre sa personalisation
 * 
 * @param string $texte
 */
function ogone_form_sha_in($texte) {
	$form = extraire_balise($texte,"form");
	$input = extraire_balises($form,"input");
	
	$args = array();
	foreach($input as $i){
		if (extraire_attribut($i, 'type')=='hidden') {
			$name = extraire_attribut($i, 'name');
			$value = extraire_attribut($i, 'value');
			$args[$name] = $value;
		}
	}

	$s = ogone_sha_in($args);
	$texte = str_replace(end($input),end($input)."<input type='hidden' name='SHASign' value='$s' />", $texte);
	return $texte;
}

?>