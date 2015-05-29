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
 * recuperer la transaction et son abonnement associe par id_transaction ou par abo_uid
 * et verifier que c'est bien le bon
 * noter dessus qu'on a eventuellement reussi le paiement,
 * passer l'abonnement en etat actif si besoin, et mettre a jour la date de fin si validite fournie
 *
 * Attention, avec certains prestataires ou modes de paiement (SEPA), on va arriver ici parce que l'abonnement a bien
 * ete cree, mais la premiere echeance n'est pas encore reglee (et ne le sera que dans 2 semaines)
 * C'est a l'abonnement de voir si il s'active temporairement en periode d'essai en attendant le vrai paiement
 * ou si il ne fait rien et attend le paiement de la premiere echeance
 * Dans ce cas de figure, on reviendra a nouveau ici une seconde fois, quand la premiere echeance sera reellement reglee
 *
 *
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

	spip_log("abos/activer_abonnement id_transaction=$id_transaction abo_uid=$abo_uid mode=$mode_paiement validite=$validite","bank");

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
