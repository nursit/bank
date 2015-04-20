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

include_spip('inc/bank');


function formulaires_configurer_bank_paiement_verifier_dist(){
	$erreurs = array();
	if ($e = _request('email_ticket_admin') AND !email_valide($e))
		$erreurs['email_ticket_admin'] = _T('form_prop_indiquer_email');


	if (!count($erreurs)){
		if ($dels=_request('action_del') AND count($dels)){
			set_request('action_del');
			foreach($dels as $del=>$v){
				set_request($del,null);
			}
		}
		if ($ups=_request('action_up') AND count($ups)){
			set_request('action_up');
			foreach($ups as $up=>$v){
				bank_deplacer_config($up,"up");
			}
		}
		if ($downs=_request('action_down') AND count($downs)){
			set_request('action_down');
			foreach($downs as $down=>$v){
				bank_deplacer_config($down,"down");
			}
		}
	}
	return $erreurs;
}

function bank_deplacer_config($nom,$sens="up"){
	include_spip('inc/config');
	$config = lire_config("bank_paiement/");

	$new = array();
	// d'abord on remet les autres configs (pas presta)
	foreach($config as $k=>$v){
		if (strncmp($k,"config_",7)!==0){
			$new[$k] = $v;
			unset($config[$k]);
		}
	}

	$kp=$vp=null;
	list($k,$v) = each($config);
	while($k AND $k!==$nom){
		$new[$k] = $v;
		$kp=$k;
		$vp=$v;
		list($k,$v) = each($config);
	}

	if ($kp AND $sens=="up" AND $k===$nom){
		array_pop($new);
		$new[$k] = $v;
		$new[$kp] = $vp;
	}
	elseif ($sens=="down" AND $k===$nom AND list($k2,$v2)=each($config)){
		$new[$k2] = $v2;
		$new[$k] = $v;
	}
	elseif($k) {
		$new[$k] = $v;
	}

	list($k,$v) = each($config);
	while($k){
		$new[$k] = $v;
		list($k,$v) = each($config);
	}
	ecrire_config("bank_paiement/",$new);
}