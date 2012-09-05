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

/**
 * #PAYER_ACTE{sips,#ID_TRANSACTION,#HASH}
 *
 * @param <type> $p
 * @return <type>
 */
function balise_PAYER_ACTE_dist($p){
	$_mode = interprete_argument_balise(1,$p);
	$_id = interprete_argument_balise(2,$p);
	$_hash = interprete_argument_balise(3,$p);
	$_titre = interprete_argument_balise(4,$p);

	$p->code = "";

	if ($_mode AND $_id AND $_hash)
		$p->code = "( (\$f=charger_fonction('acte','presta/'.$_mode.'/payer'))?\$f($_id,$_hash,$_titre):'')";

	$p->interdire_scripts = false;
	return $p;
}


/**
 * #PAYER_ABONNEMENT{sips,#ID_TRANSACTION,#HASH}
 *
 * @param <type> $p
 * @return <type>
 */
function balise_PAYER_ABONNEMENT_dist($p){
	$_mode = interprete_argument_balise(1,$p);
	$_id = interprete_argument_balise(2,$p);
	$_hash = interprete_argument_balise(3,$p);

	$p->code = "";

	if ($_mode AND $_id AND $_hash)
		$p->code = "( (\$f=charger_fonction('abonnement','presta/'.$_mode.'/payer'))?\$f($_id,$_hash):'')";

	$p->interdire_scripts = false;
	return $p;
}
?>