<?php
/**
 * Decrire une echeance abonnement pour le paiement
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
 * @param bool $force_auto
 *   true : l'echeance sera forcement prelevee automatiquement
 *   false : on peut gerer le paiement echeance manuellement en renvoyant un montant nul
 * @return array|bool
 *   montant : montant de l'echeance / si on renvoie 0 il n'y aura pas de paiement automatique mensuel, mais on recuperera les infos CB si possible (paybox) pour prendre en charge les paiements mensuels
 *   montant_ht : montant HT de l'echeance
 *   freq : en nombre de mois, pas utilise en vrai, la seule regularite fonctionnelle est "tous les mois"
 *   string wha_oid : optionnel, numero d'offre d'abonnement chez WHA/Internet+
 *
 *   false si pas d'abonnement qui correspond a cette transaction
 */
function abos_decrire_echeance_dist($id_transaction,$force_auto = true){

	$desc = array(
		'montant' => 0,
		'montant_ht' => 0,
		'freq' => 1,
		'wha_oid' => '',
	);

	// recuperer les infos en fonction de l'implementation des abonnements
	$desc = pipeline(
		'bank_abos_decrire_echeance',
		array(
			'args'=>array(
				'id_transaction'=>$id_transaction,
				'force_auto'=>$force_auto
			),
			'data' => $desc,
		)
	);

	return $desc;
}
