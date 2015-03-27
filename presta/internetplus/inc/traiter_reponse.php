<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2014 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;
include_spip('presta/internetplus/inc/wha_services');

function presta_internetplus_inc_traiter_reponse_dist($config){

	$mode = 'wha'; // historique...
	if ($config['type']=='abo')
		$mode = 'wha_abo';

	$id_transaction = 0;
	if (!$m = _request('m')) return array($id_transaction,false,false);
	$m = urldecode($m);
	$mp = false;
	
	if (!$decode=wha_unsign($m)) {
		include_spip('inc/bank');
		bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "signature invalide",
				'log' => $m
			)
		);
		return array($id_transaction,false,false);
	}

	list($unsign,$partnerId,$keyId) = $decode;
	#var_dump($unsign);
	$args = wha_extract_args($unsign);
	
	$mp = $args['v']['mp'];

	#var_dump($args);
	// recuperer le code de resultat
	$c = isset($args['c'])?$args['c']:"";
	// annulation de l'internaute
	if (preg_match(",^(OfferAuthorization|Authorize)Cancel$,i",$c)){
		spip_log($t = "wha_traiter_reponse : annulation de la transaction : $m",$mode);
		if (isset($args['v'])
		 AND is_array($mp=$v=$args['v'])
		 AND $id_transaction=intval($v['id_transaction'])) {
			$row = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction));
			if ($row['reglee']=='oui') return array($id_transaction,true,$mp);
			// sinon enregistrer echec transaction
			$date_paiement = date('Y-m-d H:i:s');
			include_spip('inc/bank');
			bank_transaction_echec($id_transaction,
				array(
					'mode'=>$mode,
					'date_paiement' => $date_paiement,
					'code_erreur' => "",
					'erreur' => "Annulation",
					'log' => var_export($args,true),
				)
			);
		}
		else {
			include_spip('inc/bank');
			bank_transaction_invalide($id_transaction,
				array(
					'mode' => $mode,
					'erreur' => "id_transaction inconnu dans args[v] lors de l'annulation, traitement impossible",
					'log' => $m
				)
			);
		}
		return array($id_transaction,false,$mp);		
	}
	
	// Code inconnu : on ne fait rien ?
	if (!preg_match(",^(OfferAuthorization|Authorize)Success$,i",$c)) {
		include_spip('inc/bank');
		bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "code reponse c inconnu, traitement impossible",
				'log' => $m
			)
		);
		return array($id_transaction,false,$mp);
	}
	// Verifier le numero de transaction, dans mp
	if (!isset($args['v'])
	 OR !is_array($v=$args['v'])
	 OR !isset($v['mp'])
	 OR !is_array($mp = $v['mp'])){
		include_spip('inc/bank');
		bank_transaction_invalide($id_transaction,
			array(
				'mode' => $mode,
				'erreur' => "mp inconnu, traitement impossible",
				'log' => $m
			)
		);

		return array($id_transaction,false,$mp);
	}

	// OK
	$traiter_reponse = charger_fonction("traiter_reponse_$mode",'presta/internetplus/inc');
	return $traiter_reponse($m,$args,$partnerId,$keyId);
}

?>