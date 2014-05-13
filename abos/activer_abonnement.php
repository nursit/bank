<?php
/**
 * Activer un abonnement suite a paiement reussi de la premiere transaction
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
 * @param int $id_transaction
 * @param string $abo_uid
 *   numero d'abonne chez le presta bancaire
 * @param string $mode_paiement
 *   mode de paiement (presta bancaire)
 * @param string $validite
 *   date de fin validite du moyen de paiement (expiration de la CB)
 *   ou "echeance" pour dire que l'abonnement s'arrete automatiquement a la prochaine echeance
 * @param int $id_auteur
 * @return bool|int
 *   false si pas reussi
 */
function abos_activer_abonnement_dist($id_transaction,$abo_uid,$mode_paiement,$validite="",$id_auteur=0){

	$id_abonnement = 0;

	// TODO :
	// recuperer la transaction et son abonnement associe par id_transaction ou par abo_uid
	// et verifier que c'est bien le bon
	// noter le paiement reussi, le passer en etat actif si besoin, et mettre a jour la date de fin si validite fournie
	$id_abonnement = pipeline(
		'bank_abos_activer_abonnement',
		array(
			'args'=>array(
				'id_transaction'=>$id_transaction,
				'abo_uid'=>$abo_uid,
				'mode_paiement'=>$mode_paiement,
				'validite'=>$validite,
				'id_auteur'=>$id_auteur,
			),
			'data' => $id_abonnement,
		)
	);


	return $id_abonnement;
}
