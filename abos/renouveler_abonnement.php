<?php
/**
 * Renouveler un abonnement suite au paiement reussi d'une echeance
 *
 * recuperer la transaction et son abonnement associe par id_transaction ou par abo_uid
 * et verifier que c'est bien le bon
 * noter sur l'abonnement que le paiement reussi,
 * et si besoin repousser sa date de fin et/ou d'echeance
 * pour qu'il reste actif jusqu'au prochain paiement
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
 *   (est utilise si par exemple on doit reactiver un abonnement expire suite a reception hors delai de la notification de paiement)
 * @return bool|int
 *   false si pas reussi
 */
function abos_renouveler_abonnement_dist($id_transaction,$abo_uid,$mode_paiement,$validite=""){

	spip_log("abos/renouveler_abonnement id_transaction=$id_transaction abo_uid=$abo_uid mode=$mode_paiement","bank");

	$id_abonnement = 0;

	$id_abonnement = pipeline(
		'bank_abos_renouveler_abonnement',
		array(
			'args'=>array(
				'id_transaction'=>$id_transaction,
				'abo_uid'=>$abo_uid,
				'mode_paiement'=>$mode_paiement,
				'validite'=>$validite,
			),
			'data' => $id_abonnement,
		)
	);


	return $id_abonnement;
}
