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

/**
 * @param string $montant
 * @param array $options
 *   string montant_ht
 *   int id_auteur
 *   string auteur_id
 *   string auteur
 *   string parrain
 *   string tracking_id
 *   bool force
 *     false pour recycler une transaction identique encore au statut commande
 *   array champs
 *     autre champs ajoutes a la transaction
 * @return int
 */
function bank_inserer_transaction_dist($montant,$options=array()){

	// support ancienne syntaxe
	// bank_inserer_transaction_dist($montant,$montant_ht,$id_auteur=0,$auteur_id="",$auteur="",$parrain="",$tracking_id="",$options=array())
	$args = func_get_args();
	if (count($args)>2 OR (isset($args[1]) AND !is_array($args[1]))){
		$options = array();
		if (isset($args[7]) AND is_array($args[7])) $options['champs'] = $args[7];
		if (isset($options['champs']['force'])){
			$options['force'] = $options['champs']['force'];
		  unset($options['champs']['force']);
		}
		if (isset($args[1])) $options['montant_ht'] = $args[1];
		if (isset($args[2])) $options['id_auteur'] = $args[2];
		if (isset($args[3])) $options['auteur_id'] = $args[3];
		if (isset($args[4])) $options['auteur'] = $args[4];
		if (isset($args[5])) $options['parrain'] = $args[5];
		if (isset($args[6])) $options['tracking_id'] = $args[6];
	}

	include_spip('base/abstract_sql');
	$force = true;
	if (isset($options['force'])){
		$force = $options['force'];
	}

	$montant=round($montant,2);
	$montant_ht = (isset($options['montant_ht'])?$options['montant_ht']:$montant);
	$montant_ht=round($montant_ht,2);

	$set = array(
		'montant'=>round($montant,2),
		'montant_ht'=>round($montant_ht,2),
		'id_auteur'=>isset($options['id_auteur'])?intval($options['id_auteur']):0,
		'auteur_id'=>isset($options['auteur_id'])?$options['auteur_id']:"",
		'auteur'=>isset($options['auteur'])?$options['auteur']:"",
		'parrain'=>isset($options['parrain'])?$options['parrain']:"",
		'tracking_id'=>isset($options['tracking_id'])?$options['tracking_id']:"",
		'date_transaction'=>date('Y-m-d H:i:s'),
	);

	if (isset($options['champs']) AND is_array($options['champs']))
		$set = array_merge($options['champs'], $set);

	// si pas insertion forcee, regarder si on a pas deja une transaction identique
	if (!$force){
		$where = array();
		foreach ($set as $k=>$v){
			if ($k!=="date_transaction"
			  AND ($k!=="montant_ht" OR isset($options['montant_ht']))){
				$where[] = "$k=".sql_quote($v,'',in_array($k,array('montant','montant_ht'))?'text':'');
			}
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
				'options' => $options,
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
				'id_objet' => $id_transaction,
	      'options' => $options,
			),
			'data' => $set
		)
	);


	return $id_transaction;
}

?>