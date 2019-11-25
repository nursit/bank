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
 *	      [(#FORMULAIRE_PAYER_ACTE{#PRIX*,
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
function formulaires_payer_acte_charger_dist($montant,$options = array()){

	// securite antibot : on ne veut pas que les bots generent des transactions
	// ni ne provoquent d'acces en base pour rien
	// (pour le cas ou ils arriveraient ici)
	// mais on mets un message au cas ou, pour ne pas empecher un humain de payer

	if (defined('_IS_BOT') AND _IS_BOT){
		$message = _L('Le paiement n\'est pas accessible aux robots. Si vous n\'êtes pas un robot, essayez avec un autre navigateur.');
		return "<div class='info'>$message</div>";
	}


	// creer la transaction
	// (ou retrouver une existante qui convient si possible et si on a un identifiant auteur ou commande ou panier)
	$opts = array(
		'force' => true,
		'champs' => array(),
	);
	// a-t-on un champ identifiant pour recyler une transaction en attente ?
	foreach (array('id_auteur','auteur_id','auteur','id_panier','id_commande') as $var){
		if (isset($options[$var])){
			$opts['force'] = false;
		}
	}
	// les champs attendus par defaut
	foreach (array('montant_ht','id_auteur','auteur_id','auteur','parrain','tracking_id') as $var){
		if (isset($options[$var])){
			$opts[$var] = $options[$var];
		}
	}
	// les champs optionnels supplementaires
	foreach (array('id_panier','id_commande','cadeau_email','cadeau_message') as $var){
		if (isset($options[$var])){
			$opts['champs'][$var] = $options[$var];
		}
	}

	// url de retour ok, echec ?
	$url_retour = array();
	foreach (array('url_retour_ok','url_retour_echec','url_retour_attente') as $var){
		if (isset($options[$var])){
			$url_retour[$var] = $options[$var];
		}
	}
	if (count($url_retour)>0){
		$opts['champs']['url_retour'] = serialize($url_retour);
	}

	// ok on recupere la transaction
	$inserer_transaction = charger_fonction("inserer_transaction","bank");
	$id_transaction = $inserer_transaction($montant,$opts);

	// si pas de transaction cree, on retourne une erreur
	if (!$id_transaction){
		$message = _L('Une erreur technique est survenue. Il n\'est pas possible de procéder au paiement.');
		return "<div class='error'>$message</div>";
	}

	$valeurs = array(
		'id_transaction' => $id_transaction,
	);
	if (isset($options['titre']))
		$valeurs['title'] = $options['titre'];

	return $valeurs;
}