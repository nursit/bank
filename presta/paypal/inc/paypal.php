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
 * Determiner le mode test en fonction d'un define ou de la config
 * @param array $config
 * @return bool
 */
function paypal_is_sandbox($config){
	$test = false;
	// _CMCIC_TEST force a TRUE pour utiliser l'adresse de test de CMCIC
	if ( (defined('_PAYPAL_SANDBOX') AND _PAYPAL_SANDBOX)
	  OR (isset($config['mode_test']) AND $config['mode_test']) ){
		$test = true;
	}
	return $test;
}

/**
 * Determiner l'URL d'appel serveur en fonction de la config
 *
 * @param array $config
 * @return string
 */
function paypal_url_serveur($config){

	if (paypal_is_sandbox($config)){
		return "https://www.sandbox.paypal.com:443/fr/cgi-bin/webscr";
	}
	else {
		return "https://www.paypal.com:443/fr/cgi-bin/webscr";
	}
}


/**
 * Recevoir la notification paypal
 * du paiement
 *
 * @param array $response
 * @param string $mode
 * @return array
 */
function paypal_traite_response($response, $config){

	$mode = $config['presta'];
	spip_log('Paypal IPN'.var_export($response,true),$mode);
		
	if (!isset($response['receiver_email']) OR ($response['receiver_email']!=$config['BUSINESS_USERNAME'])){
		spip_log($s="receiver_email errone : POST :".var_export($response,true),$mode._LOG_ERREUR);
		return array(0,false);
	}

	if (!isset($response['invoice'])){
		spip_log($s="pas de invoice specifie : POST :".var_export($response,true),$mode._LOG_ERREUR);
		spip_log($s,$mode._LOG_ERREUR);
		return array(0,false);
	}
	
	list($id_transaction,$transaction_hash) = explode('|',$response['invoice']);
	if (!$row = sql_fetsel("*","spip_transactions", "id_transaction=".intval($id_transaction) ." AND transaction_hash=".sql_quote($transaction_hash))){
		spip_log("id_transaction invalide : $id_transaction/hash$transaction_hash : POST :".var_export($response,true),$mode._LOG_ERREUR);
		return array(0,false);
	}

	if ($row['reglee']=='oui')
		return array($id_transaction,true); // cette transaction a deja ete reglee. double entree, on ne fait rien

	// verifier que le status est bien ok
	if (!isset($response['payment_status']) OR ($response['payment_status']!='Completed')){
		spip_log("Transaction $id_transaction:payment_status!=completed : POST :".var_export($response,true),$mode._LOG_ERREUR);
		$message="Votre r&egrave;glement n'a pu &ecirc;tre r&eacute;alis&eacute; par Paypal";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}
	
	// verifier que le numero de transaction au sens paypal
	// (=numero d'autorisation ici) est bien fourni
	if (!isset($response['txn_id']) OR (!$response['txn_id'])){
		spip_log("Transaction $id_transaction:pas de txn_id : POST :".var_export($response,true),$mode._LOG_ERREUR);
		$message="Nous n'avons re&ccedil;u aucune autorisation de Paypal concernant votre r&egrave;glement";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}
	
	// verifier que le numero de transaction au sens paypal
	// (=numero d'autorisation ici) n'a pas deja ete utilise
	$autorisation_id = $response['txn_id'];
	if ($id = sql_getfetsel("id_transaction","spip_transactions","autorisation_id=".sql_quote($autorisation_id)." AND mode='paypal'")){
		// une transaction existe deja avec ce numero d'autorisation
		spip_log("Transaction $id_transaction:txn_id $autorisation_id deja utilise par $id : POST :".var_export($response,true),$mode._LOG_ERREUR);
		$message="Les informations concernant votre transaction $id_transaction sont erron&eacute;es";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}

	// enregistrer immediatement le present numero d'autorisation pour ne pas risquer des requetes simultanees sur le meme id
	sql_updateq("spip_transactions",array("autorisation_id"=>$autorisation_id,"mode"=>'paypal'),"id_transaction=".intval($id_transaction));

	// une monnaie est-elle bien indique (et en EUR) ?
	if (!isset($response['mc_currency']) OR ($response['mc_currency']!='EUR')){
		spip_log("Transaction $id_transaction:devise incorrecte : POST :".var_export($response,true),$mode._LOG_ERREUR);
		$message="Les informations concernant votre transaction $id_transaction sont erron&eacute;es";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}

	// un montant est il bien renvoye ?
	if (!isset($response['mc_gross']) OR (($montant_regle=$response['mc_gross'])!=$row['montant'])){
		spip_log("Transaction $id_transaction:montant incorrect : POST :".var_export($response,true),$mode._LOG_ERREUR);
		$message="Les informations concernant votre transaction $id_transaction sont erron&eacute;es";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}

	// verifier que la notification vien bien de paypal !
	if (!bank_paypal_verifie_notification($response, $config)){
		spip_log("Transaction $id_transaction:reponse IPN!=VERIFIE : POST :".var_export($response,true),$mode._LOG_ERREUR);
		$message="Paypal n'a pu confirmer la validit&eacute; de votre r&egrave;glement concernant la transaction $id_transaction";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}

	$set = array(
		"autorisation_id"=>$autorisation_id,
		"mode"=>$mode,
		"montant_regle"=>$montant_regle,
		"date_paiement"=>date('Y-m-d H:i:s'),
		"statut"=>'ok',
		"reglee"=>'oui'
	);

	sql_updateq("spip_transactions", $set, "id_transaction=".intval($id_transaction));
	spip_log("simple_reponse : id_transaction $id_transaction, reglee",$mode);

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,array('row_prec'=>$row));
	return array($id_transaction,true);
}

/**
 * Renseigner une transaction echouee
 *
 * @param int $id_transaction
 * @param string $message
 * @return array
 */
function paypal_echec_transaction($id_transaction,$message){
	sql_updateq("spip_transactions",
	  array('message'=>$message,'statut'=>'echec'),
	  "id_transaction=".intval($id_transaction)
	);
	return array($id_transaction,false); // erreur sur la transaction
}

/**
 * Verifier que la notification de paiement vient bien de paypal !
 * @param array $args
 * @return bool
 */
function bank_paypal_verifie_notification($response, $config){
	// lire la publication du systeme PayPal et ajouter 'cmd'
	$response['cmd'] ='_notify-validate';

	// envoyer la demande de verif en post
	// attention, c'est une demande en ssl, il faut avoir un php qui le supporte
	$bank_recuperer_post_https = charger_fonction("bank_recuperer_post_https","inc");
	list($resultat,$erreur,$erreur_msg) = $bank_recuperer_post_https(paypal_url_serveur($config),$response);

	if (strncmp($resultat,'VERIFIE',7)==0)
		return true;

	spip_log("Retour IPN :$resultat:Erreur$erreur:$erreur_msg: POST :".var_export($response,true),$config['presta']._LOG_ERREUR);
	return false;
}

?>