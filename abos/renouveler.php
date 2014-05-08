<?php
/**
 * Renouveler un abonnement
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
 * Fonction appelee suite a notification par le presta (paybox) de paiement automatique de l'echeance
 * le presta fourni le numero d'abonne selon sa convention, et on doit creer une transaction correspondant a l'echeance
 *
 * @param string $id
 *   numero d'abonnement : numero interne a l'implementation, ou uid fourni par le presta, prefixe par uid:
 * @return bool|int
 *   false si on a pas pu renouveler
 *   id_transaction du renouvellement si reussi
 */
function abos_renouveler_dist($id){

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
		'bank_abos_renouveler',
		array(
			'args'=>array('id'=>$id),
			'data' => false,
		)
	);

	if ($id_transaction)
		return $id_transaction;

	return false;
}
