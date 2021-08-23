<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2019 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')){
	return;
}

/**
 * Recuperer une url en https avec curl ou recuperer_url sinon
 *
 * @param string $url
 * @param array|string $datas
 *   $datas peut etre un tableau de paire=>valeur, ou une chaine de get paire=valeur&...
 * @param string $user_agent
 * @param string $proxy_host
 * @param string $proxy_port
 * @return array
 */
function inc_bank_recuperer_post_https_dist($url, $datas = '', $user_agent = '', $proxy_host = '', $proxy_port = ''){

	$erreur = false;
	$erreur_msg = "";

	if (!function_exists('curl_init')){
		include_spip('inc/distant');
		$nvpreq = $datas;
		if (is_array($datas) AND count($datas)){
			$nvpreq = http_build_query($datas);
		}
		spip_log("bank_recuperer_post_https sur $url via recuperer_url : $nvpreq", 'bank' . _LOG_DEBUG);

		$options = array(
			'taille_max' => 1048576,
			'datas' => $datas,
			'boundary' => null
		);
		$response = recuperer_url($url, $options);
		if (!$response or $response['status'] !== 200) {
			spip_log("bank_recuperer_post_https sur $url via recuperer_url : " . json_encode($response), 'bank' . _LOG_ERREUR);
			$erreur = true;
			$erreur_msg = "Echec recuperer_url";
			if (!empty($response['status'])) {
				$erreur_msg .= " | Status " . $response['status'];
			}
			$response = '';
		}
		else {
			$response = $response['page'];
		}
	} else {
		//setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		//curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

		//turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		if (!defined('CURL_SSLVERSION_TLSv1_2')){
			define('CURL_SSLVERSION_TLSv1_2', 6);
		}
		curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

		//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		//curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__)."/cert/api_cert_chain.crt");

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);

		if ($proxy_host) {
			curl_setopt ($ch, CURLOPT_PROXY, $proxy_host . ($proxy_port ? ":$proxy_port" : ''));
		}

		//NVPRequest for submitting to server
		$nvpreq = $datas;
		if (is_array($datas) AND count($datas)){
			$nvpreq = http_build_query($datas);
		}
		spip_log("bank_recuperer_post_https sur $url via curl : $nvpreq", 'bank' . _LOG_DEBUG);

		if (!$user_agent){
			$user_agent = "SPIP/Bank";
		}

		//setting the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close', "User-Agent: $user_agent"));

		//getting response from server
		$response = curl_exec($ch);
		$erreur = curl_errno($ch);
		$erreur_msg = curl_error($ch);
		if (!$erreur){
			//closing the curl
			curl_close($ch);
		}
		else {
			spip_log("bank_recuperer_post_https sur $url via curl : " . $response, 'bank' . _LOG_ERREUR);
		}
	}

	return array($response, $erreur, $erreur ? $erreur_msg : '');
}

