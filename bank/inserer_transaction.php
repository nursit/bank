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
 * @param string $montant
 * @param string $montant_ht
 * @param int $id_auteur
 * @param string $auteur_id
 * @param string $auteur
 * @param string $parrain
 * @param string $tracking_id
 * @param array $options
 *   force=false pour recycler une transaction identique encore au statut commande
 *   autre champs ajoutes a la transaction
 * @return bool|int|mixed
 */
function bank_inserer_transaction_dist($montant,$montant_ht,$id_auteur=0,$auteur_id="",$auteur="",$parrain="",$tracking_id="",$options=array()){
	include_spip('base/abstract_sql');
	$force = true;
	if (isset($options['force'])){
		$force = $options['force'];
		unset($options['force']);
	}

	$montant=round($montant,2);
	$montant_ht=round($montant_ht,2);

	$set = array(
		'montant'=>round($montant,2),
		'montant_ht'=>round($montant_ht,2),
		'id_auteur'=>intval($id_auteur),
		'auteur_id'=>$auteur_id,
		'auteur'=>$auteur,
		'parrain'=>$parrain,
		'tracking_id'=>$tracking_id,
		'date_transaction'=>date('Y-m-d H:i:s'),
	);

	if ($options)
		$set = array_merge($options, $set);

	// si pas insertion forcee, regarder si on a pas deja une transaction identique
	if (!$force){
		$where = array();
		foreach ($set as $k=>$v){
			if ($k!=="date_transaction")
				$where[] = "$k=".sql_quote($v);
		}
		$where[] = "statut=".sql_quote("commande");
		$where[] = "date_transaction>".sql_quote(date('Y-m-d H:i:s',strtotime("-1 day")));
		if ($id_transaction = sql_getfetsel("id_transaction","spip_transactions",$where))
			return $id_transaction;
	}

	// Envoyer aux plugins
	$set = pipeline('pre_insertion',
		array(
			'args' => array(
				'table' => 'spip_transactions',
			),
			'data' => $set
		)
	);
	$id_transaction = sql_insertq('spip_transactions',$set);
	if (!$id_transaction
		OR !$date = sql_getfetsel('date_transaction','spip_transactions','id_transaction='.intval($id_transaction))
	)
		return 0;

	// un hash pour securiser l'acces aux transactions
	$transaction_hash = substr(md5("$id_transaction:$montant_ht:$montant:$date"),0,8);
	$transaction_hash = hexdec("0x".$transaction_hash);
	if (!sql_updateq("spip_transactions",array('statut'=>'commande','transaction_hash'=>$transaction_hash),"id_transaction=".intval($id_transaction)))
		return 0;

	pipeline('post_insertion',
		array(
			'args' => array(
				'table' => 'spip_transactions',
				'id_objet' => $id_transaction
			),
			'data' => $set
		)
	);


	return $id_transaction;
}

?>