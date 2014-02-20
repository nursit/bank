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
 * Enregistrer le reglement effectif d'une transaction
 * On peut passer ici 2 fois pour une meme transaction :
 * - lors de la notification serveur a serveur
 * - lors du retour de l'internaute par redirection depuis le presta bancaire
 *
 * @param int $id_transaction
 * @param array $options
 *   string $message
 *   array $row_prec
 *   bool $notifier
 */
function bank_regler_transaction_dist($id_transaction,$options = array()){

	// support ancienne syntaxe
	// bank_regler_transaction_dist($id_transaction,$message="",$row_prec=null,$notifier = true)
	$args = func_get_args();
	if (count($args)>2 OR (isset($args[1]) AND !is_array($args[1]))){
		$options = array();
		if (isset($args[1])) $options['message'] = $args[1];
		if (isset($args[2])) $options['row_prec'] = $args[2];
		if (isset($args[3])) $options['notifier'] = $args[3];
	}

	$message = (isset($options['message'])?$options['message']:"");
	$notifier = (isset($options['notifier'])?$options['notifier']:true);

	if (!strlen($message)) {
		$bank_messager_reglement_enregistre = charger_fonction('bank_messager_reglement_enregistre','inc');
		$message = $bank_messager_reglement_enregistre($id_transaction);
	}

	if (!isset($options['row_prec']))
		$options['row_prec'] = sql_fetsel("*","spip_transactions","id_transaction=".intval($id_transaction));
	$row_prec = $options['row_prec'];

	// on pose un flag dans la session pour permettre la pose eventuelle de tag
	// sur la prochaine page
	// si c'est un visiteur public
	if (!test_espace_prive())
		$_SESSION['id_transaction_achevee'] = $id_transaction;

	// ne pas jouer 2 fois le traitement du reglement !
	if (!$row_prec OR
		(($row_prec['reglee']=='oui') AND $row_prec['finie']))
		return;

	$notifier = ($notifier AND $row_prec['reglee']!='oui');

	// d'abord un pipeline de facturation
	$message = pipeline('bank_facturer_reglement',array(
		'args'=>array(
			'id_transaction'=>$id_transaction,
			'new'=>$row_prec['reglee']!=='oui',
			'confirm'=>$row_prec['reglee']=='oui',
			'notifier'=>$notifier,
			'avant'=>$row_prec,
			'options' => $options,
		),
		'data'=>$message)
	);

	// ensuite un pipeline de traitement, notification etc...
	$message = pipeline('bank_traiter_reglement',array(
		'args'=>array(
			'id_transaction'=>$id_transaction,
			'new'=>$row_prec['reglee']!=='oui',
			'confirm'=>$row_prec['reglee']=='oui',
			'notifier'=>$notifier,
			'avant'=>$row_prec,
			'options' => $options,
		),
		'data'=>$message)
	);

	sql_updateq("spip_transactions",array('message'=>$message,'finie'=>1),"id_transaction=".intval($id_transaction));

	// notifier aux admins avec un ticket caisse
	if ($notifier) {
		$bank_editer_ticket_admin = charger_fonction('bank_editer_ticket_admin','inc');
		$bank_editer_ticket_admin($id_transaction);


		// trigger la notification
		// le pipeline a le meme format que bank_redirige_apres_retour_transaction
		// cela permet de factoriser le code
		$row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction));
		pipeline('trig_bank_notifier_reglement',array(
			'args' => array(
				'mode'=>$row['mode'],
				'type'=>'acte',
				'succes'=>true,
				'id_transaction'=>$id_transaction,
				'row'=>$row,
				'options' => $options,
			),
			'data' => '')
		);
	}
}


?>