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
 * Recevoir la notification paypal
 * du paiement
 *
 * @return array
 */
function bank_paypal_recoit_notification(){
	$valeurs = array();

	foreach ($_POST as $key => $value)
		$valeurs[$key] = $value;
	spip_log('Paypal IPN'.var_export($valeurs,true),'paypal');
		
	if (!isset($valeurs['receiver_email']) OR ($valeurs['receiver_email']!=_PAYPAL_BUSINESS_USERNAME)){
		spip_log("receiver_email errone : POST :".var_export($valeurs,true),'paypal_invalid_IPN');
		return array(0,false);
	}
	if (!isset($valeurs['invoice'])){
		spip_log("pas de invoice specifie : POST :".var_export($valeurs,true),'paypal_invalid_IPN');
		return array(0,false);
	}
	
	list($id_transaction,$transaction_hash) = explode('|',$valeurs['invoice']);
	if (!$row = sql_fetsel("*","spip_transactions",
					"id_transaction=".intval($id_transaction)
					." AND transaction_hash=".sql_quote($transaction_hash))){

		spip_log("id_transaction invalide : $id_transaction/hash$transaction_hash : POST :".var_export($value,true),'paypal_invalid_IPN');
		return array(0,false);
	}

	if ($row['reglee']=='oui')
		return array($id_transaction,true); // cette transaction a deja ete reglee. double entree, on ne fait rien

	// verifier que le status est bien ok
	if (!isset($valeurs['payment_status']) OR ($valeurs['payment_status']!='Completed')){
		spip_log("Transaction $id_transaction:payment_status!=completed : POST :".var_export($valeurs,true),'paypal_invalid_IPN');
		$message="Votre r&egrave;glement n'a pu &ecirc;tre r&eacute;alis&eacute; par Paypal";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}
	
	// verifier que le numero de transaction au sens paypal
	// (=numero d'autorisation ici) est bien fourni
	if (!isset($valeurs['txn_id']) OR (!$valeurs['txn_id'])){
		spip_log("Transaction $id_transaction:pas de txn_id : POST :".var_export($valeurs,true),'paypal_invalid_IPN');
		$message="Nous n'avons re&ccedil;u aucune autorisation de Paypal concernant votre r&egrave;glement";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}
	
	// verifier que le numero de transaction au sens paypal
	// (=numero d'autorisation ici) n'a pas deja ete utilise
	$autorisation_id = $valeurs['txn_id'];
	if ($id = sql_getfetsel("id_transaction","spip_transactions","autorisation_id=".sql_quote($autorisation_id)." AND mode='paypal'")){
		// une transaction existe deja avec ce numero d'autorisation
		spip_log("Transaction $id_transaction:txn_id $autorisation_id deja utilise par $id : POST :".var_export($valeurs,true),'paypal_invalid_IPN');
		$message="Les informations concernant votre transaction $id_transaction sont erron&eacute;es";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}

	// enregistrer immediatement le present numero d'autorisation pour ne pas risquer des requetes simultanees sur le meme id
	sql_updateq("spip_transactions",array("autorisation_id"=>$autorisation_id,"mode"=>'paypal'),"id_transaction=".intval($id_transaction));

	// une monnaie est-elle bien indique (et en EUR) ?
	if (!isset($valeurs['mc_currency']) OR ($valeurs['mc_currency']!='EUR')){
		spip_log("Transaction $id_transaction:devise incorrecte : POST :".var_export($valeurs,true),'paypal_invalid_IPN');
		$message="Les informations concernant votre transaction $id_transaction sont erron&eacute;es";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}

	// un montant est il bien renvoye ?
	if (!isset($valeurs['mc_gross']) OR (($montant_regle=$valeurs['mc_gross'])!=$row['montant'])){
		spip_log("Transaction $id_transaction:montant incorrect : POST :".var_export($valeurs,true),'paypal_invalid_IPN');
		$message="Les informations concernant votre transaction $id_transaction sont erron&eacute;es";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}

	// verifier que la notification vien bien de paypal !
	if (!bank_paypal_verifie_notification($valeurs)){
		spip_log("Transaction $id_transaction:reponse IPN!=VERIFIE : POST :".var_export($valeurs,true),'paypal_invalid_IPN');
		$message="Paypal n'a pu confirmer la validit&eacute; de votre r&egrave;glement concernant la transaction $id_transaction";
		return paypal_echec_transaction($id_transaction,$message); // erreur sur la transaction
	}

	sql_updateq("spip_transactions",
		array(
		"autorisation_id"=>$autorisation_id,
		"mode"=>'paypal',
		"montant_regle"=>$montant_regle,
		"date_paiement"=>'NOW()',
		"statut"=>'ok',
		"reglee"=>'oui'
		),
		"id_transaction=".intval($id_transaction));
	spip_log("simple_reponse : id_transaction $id_transaction, reglee",'paypal');

	$regler_transaction = charger_fonction('regler_transaction','bank');
	$regler_transaction($id_transaction,"",$row);
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
function bank_paypal_verifie_notification($args){
	// lire la publication du systeme PayPal et ajouter 'cmd'
	$args['cmd'] ='_notify-validate';

	// envoyer la demande de verif en post
	// attention, c'est une demande en ssl, il faut avoir un php qui le supporte
	$bank_recuperer_post_https = charger_fonction("bank_recuperer_post_https","inc");
	list($resultat,$erreur,$erreur_msg) = $bank_recuperer_post_https(_PAYPAL_URL_SERVICES,$args);

	if (strncmp($resultat,'VERIFIE',7)==0)
		return true;

	spip_log("Retour IPN :$resultat:Erreur$erreur:$erreur_msg: POST :".var_export($args,true),'paypal_invalid_IPN');
	return false;
}

?>