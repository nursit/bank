<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('presta/paybox/inc/paybox');
include_spip('inc/date');

// il faut avoir un id_transaction et un transaction_hash coherents
// pour se premunir d'une tentative d'appel exterieur
function presta_paybox_call_response_dist($response=null){

	if (!$response)
		// recuperer la reponse en post et la decoder
		$response = paybox_response();

	if (!$response) {
		return array(0,false);
	}

	if ($response['ETAT_PBX']=='PBX_RECONDUCTION'){
		// c'est un revouvellement initie par paybox : creer la transaction maintenant si besoin !
		if ($renouveler = charger_fonction('renouveler','abos',true)){
			// on reinjecte le bon id de transaction ici si fourni
			if ($id_transaction = $renouveler("uid:".$response['abo'])){
				$response['id_transaction'] = $id_transaction;
			}
		}
	}
	
	// depouillement de la transaction
	list($id_transaction,$success) =  paybox_traite_reponse_transaction($response);

	if ($response['abo'] AND $id_transaction AND $success) {
	
		// recuperer la date de validite de la carte
		// ne sert pas ici, car on fixe la fin de l'abo a echeance+1jour
		// ce qui le resiliera automatiquement en cas de non reconduction
		$date_fin = mktime(0,0,0,substr($response['valid'],2,2),27,2000+substr($response['valid'],0,2))+5*24*3600;
		$date_fin = mktime(0,0,0,date('m',$date_fin),date('d'),date('Y',$date_fin));
		$date_fin = date('Y-m-d H:i:s',$date_fin);

		spip_log('response:'.var_export($response,true),'payboxdb');
		spip_log('date_fin:'.$date_fin,'payboxdb');
		
		// id_transaction contient toute la trame IDB_xx deriere le numero
		// on ne retient que la valeur entiere
		$id_transaction = intval($id_transaction);

		$activer_abonement = charger_fonction('activer_abonnement','abos');
		$activer_abonement($id_transaction,$response['abo'],'paybox',$date_fin);
	}
	return array($id_transaction,$success);	
}
?>
