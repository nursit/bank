<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2018 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('base/abstract_sql');


/**
 * Cette fonction genere  une partie du message de l'email de confirmation
 * avec l'url du site sur lequel l'achat s'est effectue
 *
 * @param <type> $id_transaction
 * @return <type>
 */
function inc_bank_messager_reglement_enregistre_dist($id_transaction=0)
{	
	//Le message final a envoyer
	$message='';
	//Le nom du site a inserer dans le message final
	$site='';
	
	$parrain=bank_identifier_parrain($id_transaction);
	$site=bank_identifier_host($parrain);

	$host = preg_replace(';^[a-z]{3,5}://;','',$site);
	$message = _T('bank:confirme_reglement_pris_en_compte',array('site'=>"<a href='$site'>$host</a>"));

	return $message;
}

/*
*
* Cette fonction va recuperer le parrain s'il existe en fonction de l'id de transaction
* id_transaction correspond a l'id de la transaction concernee
*/
function bank_identifier_parrain($id_transaction)
{
	$parrain='';
	
	/*Si id transaction inferieur a zero, cas impossible on retourne un parrain vide*/
	if($id_transaction>0){
		/*On essaie de recuperer le parrain en fonction de l'id de transaction*/
		$parrain=sql_getfetsel("parrain","spip_transactions","id_transaction=".intval($id_transaction));

	}
	return $parrain;	
}

/*
*Cette fonction va permettre en fonction du parrain de retrouver le host qu'il faut 
*utiliser en se basant sur la globale bank_nom_site
*
*/
function bank_identifier_host($parrain)
{
	//Le nom du site a inserer dans le message final
	$site='';
	
	//On recupere le host et le parrain afin de retrouver dans la variable globale bank_nom_site l'url du site a presenter
	$host_actuel="http://".$_SERVER['HTTP_HOST'];

	if (isset($GLOBALS['bank_nom_site'][$host_actuel][$parrain]))
		$site=$GLOBALS['bank_nom_site'][$host_actuel][$parrain];
		//si le host actuel n'appartient pas a la liste des hosts enregistree,on mets le host par defaut en se basant
		//sur la meta adresse_site
	else
		$site = $GLOBALS['meta']['adresse_site'];
	
	return $site;
}


?>