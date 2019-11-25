<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * Payer un acte dont on passe les infos en montant
 * la transaction est automatiquement generee si besoin
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2018 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * @param string $montant
 *   montant a payer
 *
 * Exemple d'utilisation dans une boucle commande :
 * ```
 * 		<BOUCLE_commande(COMMANDES){id_commande}>
 *	      <h1>Commande ##ID_COMMANDE  - Ref #REFERENCE</h1>
 *	      [(#FORMULAIRE_PAYER_ABONNEMENT{#PRIX*,
 *		      #ARRAY{
 *			      montant_ht,PRIX_HT*,
 *			      id_commande,#ID_COMMANDE,
 *			      id_auteur,#ID_AUTEUR,
 *		      }
 *	      })]
 *		</BOUCLE_commande>
 * ```
 *
 * @param array $options
 *   string montant_ht : montant ht en euros
 *   int id_auteur : auteur (spip) loge qui realise la transaction
 *   string auteur_id : autre identifiant auteur a defaut
 *   string auteur : a defaut info auteur autre (email, n°...)
 *   string parrain : sponsor de la transaction (apporteur affilie, url site source..)
 *   int tracking_id : numero de tracking affiliateur
 *   int id_panier : numero de panier eventuel
 *   int id_commande : numero de commande eventuel
 *   string cadeau_email : achat a l'attention d'une tierce personne
 *   string cadeau_message : achat a l'attention d'une tierce personne
 *   string url_retour_ok : url de retour en cas de succes (par defaut spip.php?page=bank_retour_ok)
 *   string url_retour_echec : url de retour en cas d'echec (par defaut spip.php?page=bank_retour_echec)
 *   string url_retour_attente : url de retour en de paiement async en attente (type virement/cheque) ave(par defaut spip.php?page=bank_retour_attente)
 *   string titre : titre eventuel du formulaire de paiement
 *
 * @return array|string
 */
function formulaires_payer_abonnement_charger_dist($montant,$options = array()){

	// meme preparation que pour le paiement a l'acte
	$charger = charger_fonction("charger","formulaires/payer_acte");
	$valeurs = $charger($montant, $options);

	return $valeurs;
}