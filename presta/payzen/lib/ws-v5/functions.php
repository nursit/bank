<?php

function setHeaders($shopId, $requestId, $timestamp, $mode, $authToken, $key, $client){
//Création des en-têtes shopId, requestId, timestamp, mode et authToken
	$ns = 'http://v5.ws.vads.lyra.com/Header/';
	$headerShopId = new SOAPHeader ($ns, 'shopId', $shopId);
	$headerRequestId = new SOAPHeader ($ns, 'requestId', $requestId);
	$headerTimestamp = new SOAPHeader ($ns, 'timestamp', $timestamp);
	$headerMode = new SOAPHeader ($ns, 'mode', $mode);
	$authToken = getAuthToken($requestId, $timestamp, $key);
	$headerAuthToken = new SOAPHeader ($ns, 'authToken', $authToken);
//Ajout des en-têtes dans le SOAP Header
	$headers = array(
		$headerShopId,
		$headerRequestId,
		$headerTimestamp,
		$headerMode,
		$headerAuthToken
	);
	$client->__setSoapHeaders($headers);
}

function setJsessionId($client){
	$cookie = $_SESSION['JSESSIONID'];
	$client->__setCookie('JSESSIONID', $cookie);
	return $cookie;
}

/**
 *
 *
 * @param $client
 * @return string $JSESSIONID
 */
function getJsessionId($client){
//récupération de l'entête de la réponse
	$header = ($client->__getLastResponseHeaders());
	if (!preg_match("#JSESSIONID=([A-Za-z0-9\._]+)#", $header, $matches)){
		return "Aucun ID de Session Renvoyé."; //Ce cas ne devrait jamais se présenter;
		die;
	}
	$JSESSIONID = $matches[1];
	$_SESSION['JSESSIONID'] = $JSESSIONID;
//print_r($JSESSIONID);
	return $JSESSIONID;
}

function formConstructor($threeDsAcsUrl, $threeDSrequestId, $threeDsEncodedPareq,
                         $threeDsServerResponseUrl){
	$msg = ('
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8"/>
<title>3DS</title>
<script type="text/javascript">
<!--
function submitForm(){
document.redirectForm.submit();
}
-->
</script>
</head>
<body id="lyra" onLoad="setTimeout(\'submitForm()' . '\',500);">
<div id="container">
<div id="paymentSolutionInfo">
<div id="title">&nbsp;</div>
</div>
<hr class="ensureDivHeight"/>
<br/>
<br/>
<br/>
<br/>
<br/>
<form name="redirectForm" action="' . $threeDsAcsUrl . '" method="POST">
<input type="hidden" name="PaReq" value="' . $threeDsEncodedPareq . '"/>
<input type="hidden" name="TermUrl" value="' . $threeDsServerResponseUrl . '"/>
<input type="hidden" name="MD" value="' . $threeDSrequestId . '"/>
<noscript><input type="submit" name="Go" value="Click to continue"/></noscript>
</form>
<div id="backToBoutiqueBlock"> </div>
<div id="footer"> </div>
</div>
</body>
</html>'
	);
	echo $msg;
}

