<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2015 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function action_bank_response_dist($cancel=null, $auto=null, $presta=null){
	
		$id_transaction = 0;
		$auto = ($auto ? "auto":"");
		$result = false;

		// intercepter les retours depuis un presta actif
		if (!$presta) $presta = _request('bankp');
		include_spip('inc/bank');
		if ($presta
			AND $config = bank_config($presta)
			AND !isset($config['erreur'])
			AND $config['actif']){

			$presta = $config['presta']; // en cas de renommage

			if (!$auto OR !$call_response = charger_fonction('autoresponse',"presta/$presta/call",true))
				$call_response = charger_fonction('response',"presta/$presta/call");

			if ($cancel)
				define('_BANK_CANCEL_TRANSACTION',true);
			spip_log('call_'.$auto.'response : '.$_SERVER['REQUEST_URI'],"$presta$auto");
			list($id_transaction,$result)=$call_response();
			spip_log('call_'.$auto.'response : '."$id_transaction/$result","$presta$auto");
		}
		else {
			spip_log("Prestataire $presta inconnu ou inactif",'bank_response'._LOG_ERREUR);
		}

		// fall back si le presta n'a rien renvoye de lisible
		// et qu'on a bien id=id_transaction;hash dans l'url
		if (!$result
		 AND !$id_transaction
		 AND $id=_request('id')
		 AND $id=explode(';',$id)
		 AND count($id)==2
		 AND $id_transaction=reset($id)
		 AND $hash=end($id)) {
			$id_transaction = sql_getfetsel ("id_transaction","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($hash));
			if ($id_transaction){
				$set = array(
					'mode'=>$presta,
					'statut'=>'echec',
					'message'=>'Transaction annul&eacute;e',
				);
				sql_updateq("spip_transactions", $set, 'id_transaction='.intval($id_transaction)." AND statut=".sql_quote('commande'));
			}
		}

		// notifier les reglement en echec/annule
		if (!$result
			AND $id_transaction
		  AND $row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction))){
			pipeline('trig_bank_reglement_en_echec', array(
					'args' => array(
						'statut'=>'echec',
						'mode' => $row['mode'],
						'type' => $row['abo_uid']?'abo':'acte',
						'id_transaction' => $id_transaction,
						'row' => $row,
					),
					'data' => '')
			);
		}


		if (!$auto){
			$abo = sql_getfetsel("abo_uid","spip_transactions","id_transaction=".intval($id_transaction));
			return redirige_apres_retour_transaction($presta,!$abo?'acte':'abo',$cancel?false:$result,$id_transaction);
		}
		die(); // mourir silencieusement

}


/**
 * cette fonction doit etre appelee avec un $id_transaction securise
 * jamais avec un $id_transaction qui provient directement de l'url sans verification
 *
 * @param string $mode
 * @param string $acte_ou_abo
 * @param bool $succes
 * @param int $id_transaction
 * @return string|void
 */
function redirige_apres_retour_transaction($mode,$acte_ou_abo,$succes,$id_transaction=0){
	$redirect = "";
	$row = false;
	if ($id_transaction = intval($id_transaction)){
		// attraper les infos sur la transaction
		$row = sql_fetsel('*','spip_transactions','id_transaction='.intval($id_transaction));
	}

	// cas de paiement par un admin (cheque...)
	// renvoyer dans le prive
	$id_auteur = sql_getfetsel("id_auteur","spip_transactions","id_transaction=".intval($id_transaction));
	if ($id_transaction
		AND isset($GLOBALS['visiteur_session']['id_auteur'])
	  AND (test_espace_prive() OR $GLOBALS['visiteur_session']['id_auteur']!=$id_auteur)
		AND include_spip("inc/autoriser")
	  AND autoriser("regler","transaction",$id_transaction)){

		$redirect = generer_url_ecrire("transactions","id_transaction=".$id_transaction,true);
	}

	if (!$redirect){
		// si des urls retour ok ou echec sont definies pour cette transaction
		// fournies par #FORMULAIRE_PAYER_ACTE
		if ($row['url_retour'] AND $urls = unserialize($row['url_retour'])){
			if ($succes AND isset($urls['url_retour_ok']))
				$redirect = $urls['url_retour_ok'];
			elseif (!$succes AND isset($urls['url_retour_echec']))
				$redirect = $urls['url_retour_echec'];
		}

		// par defaut on revient sur une des pages reglees en define()
		if (!strlen($redirect)){
			// _BANK_ACTE_NORMAL_RETURN_URL
			// _BANK_ACTE_CANCEL_RETURN_URL
			// _BANK_ABO_NORMAL_RETURN_URL
			// _BANK_ABO_CANCEL_RETURN_URL
			$acte_ou_abo = ($acte_ou_abo=='acte' ? 'ACTE' : 'ABO');
			$c = "_BANK_" . $acte_ou_abo . "_NORMAL_RETURN_URL";

			if ($succes){
				if (defined($c))
					$redirect = constant($c);
				else
					$redirect = generer_url_public('bank_retour_ok');
			} else {
				if (defined($c))
					$redirect = constant($c);
				else
					$redirect = generer_url_public('bank_retour_echec');
			}
		}


		// ajouter id_transaction et transaction_hash sur l'url de retour dans tous les cas
		if (strlen($redirect)){
			$redirect = parametre_url($redirect,'type',strtolower($acte_ou_abo),'&');
			if ($row AND $row['transaction_hash']) {
				$redirect = parametre_url($redirect,'id_transaction',$id_transaction,'&');
				$redirect = parametre_url($redirect,'transaction_hash',$row['transaction_hash'],'&');
			}
		}

	}


	// permettre de definir autrement l'url de redirection
	$redirect = pipeline('bank_redirige_apres_retour_transaction',
		array(
		'args' => array(
			'mode'=>$mode,
			'type'=>$acte_ou_abo,
			'succes'=>$succes,
			'id_transaction'=>$id_transaction,
			'row'=>$row
		),
		'data' => $redirect)
	);

	#var_dump($redirect);die();
	if (strlen($redirect)){
		include_spip('inc/headers');
		if (function_exists("redirige_formulaire"))
			return redirige_formulaire($redirect);
		else
			redirige_par_entete($redirect);
		exit;
	}

	//on ne devrait jamais arriver la !
	if ($succes)
		echo "Transaction $mode $acte_ou_abo $id_transaction terminee OK";
	else
		echo "Transaction $mode $acte_ou_abo $id_transaction annulee";
	die();
}
?>