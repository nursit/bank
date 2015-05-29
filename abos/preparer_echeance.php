<?php
/**
 * Preparer l'echeance de renouvellement d'un abonnement
 *
 * @plugin     bank
 * @copyright  2014
 * @author     cedric
 * @licence    GNU/GPL
 * @package    SPIP\Abos\API
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('base/abstract_sql');

/**
 * Fonction appelee suite a notification par le presta (paybox/payzen) de paiement automatique de l'echeance
 * le presta fourni le numero d'abonne selon sa convention,
 * et on doit creer une transaction correspondant au paiement attendu de l'echeance
 *
 * @param string $id
 *   numero d'abonnement : numero interne a l'implementation, ou uid fourni par le presta, prefixe par uid: dans ce cas
 * @return bool|int
 *   false si on a pas pu renouveler
 *   id_transaction du renouvellement si reussi
 */
function abos_preparer_echeance_dist($id){

	/*
	if (strncmp($id,"uid:",4)==0){
		$where = "abonne_uid=".sql_quote(substr($id,4));
	}
	else {
		$where = "id_abonnement=".intval($id);
	}
	*/

	// recuperer les infos en fonction de l'implementation des abonnements
	$id_transaction = pipeline(
		'bank_abos_preparer_echeance',
		array(
			'args'=>array('id'=>$id),
			'data' => false,
		)
	);

	if ($id_transaction)
		return $id_transaction;

	// DEPRECIE, pour compat ascendante

	// sinon on essaye sur l'ancien pipeline
	// recuperer les infos en fonction de l'implementation des abonnements
	$id_transaction = pipeline(
		'bank_abos_renouveler',
		array(
			'args'=>array('id'=>$id),
			'data' => false,
		)
	);

	if ($id_transaction)
		return $id_transaction;

	// sinon on essaye d'appeler l'ancien abos/renouveler
	if ($renouveler = charger_fonction('renouveler','abos',true)){
		return $renouveler($id);
	}

	return false;
}