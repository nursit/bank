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

/**
 * Recuperer une url en https avec curl ou recuperer_page sinon
 *
 * @param string $url
 * @param array|string $datas
 *   $datas peut etre un tableau de paire=>valeur, ou une chaine de get paire=valeur&...
 * @return array
 */
function inc_bank_recuperer_post_https_dist($url,$datas='') {

	if (!function_exists('curl_init')){
		include_spip('inc/distant');
		if (is_string($datas) AND strlen($datas)){
			parse_str($datas, $args); // passer en tableau
			$datas = $args;
		}
		$response = recuperer_page($url,false,false,1048576,$datas);
		$erreur = $response===false;
		$erreur_msg = "recuperer_page impossible";
	}
	else {
		//setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		//turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);

		//if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
		//Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php
		#if(_PAYPAL_USE_PROXY)
		#curl_setopt ($ch, CURLOPT_PROXY, _PAYPAL_PROXY_HOST.":"._PAYPAL_PROXY_PORT);

		//NVPRequest for submitting to server
		$nvpreq="";
		if (is_array($datas) AND count($datas)){
			$nvpreq = http_build_query($datas);
		}

		//setting the nvpreq as POST FIELD to curl
		curl_setopt($ch,CURLOPT_POSTFIELDS,$nvpreq);

		//getting response from server
		$response = curl_exec($ch);
		$erreur = curl_errno($ch);
		$erreur_msg = curl_error($ch);
		if (!$erreur){
		 //closing the curl
			curl_close($ch);
		}
	}
	return array($response,$erreur,$erreur?$erreur_msg:'');
}
?>