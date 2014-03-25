<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2014 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function wha_secret_key($partnerId,$keyId){
	if (defined($s = '_WHA_SECRET_'.intval($partnerId)) AND constant($s))
		return constant($s);
	// sinon on log l'absence de cle
	spip_log("wha_secret_key : $s non definie","internetplus"._LOG_ERREUR);
	return "nokey";
}

// Calculate HMAC according to RFC2104
// http://www.ietf.org/rfc/rfc2104.txt
function wha_hmac($key, $data, $hash = 'md5', $blocksize = 64) {
  if (strlen($key)>$blocksize) {
    $key = pack('H*', $hash($key));
  }
  $key  = str_pad($key, $blocksize, chr(0));
  $ipad = str_repeat(chr(0x36), $blocksize);
  $opad = str_repeat(chr(0x5c), $blocksize);
  return $hash(($key^$opad) . pack('H*', $hash(($key^$ipad) . $data)));
}

function wha_formate_args($nom,$valeur,$sep=';'){
	if (!is_array($valeur))
		return ($nom?"$nom=":"")."$valeur$sep";
	else {
		$res = ($nom?"$nom={":"");
		foreach($valeur as $k=>$v){
			$res .= wha_formate_args($k,$v,$sep);
		}
		if (is_array($v)){$res = substr(trim($res),0,-1);}
		if (is_array($valeur) && $nom=='v'){$res = trim($res);}
		$res .= ($nom?"}$sep":'');
		return $res;
	}
}
function wha_extract_args($chaine,$sep=';'){
	$res = array();
	// commencer par extraire les sous tableaux
	while (preg_match(",([^=$sep]+)=\s*\{,",$chaine,$regs)){
		$sub = $regs[0];
		$p = strpos($chaine,$sub);
		$cur = $p+strlen($sub);
		$open = 1;
		while ($open>0 AND ($cur<strlen($chaine))){
			$cur++;
			if (substr($chaine,$cur,1)=='{')
				$open++;
			if (substr($chaine,$cur,1)=='}')
				$open--;
		}
		$sub2 = substr($chaine,$p+strlen($sub),$cur-$p-strlen($sub));
		$sub = substr($chaine,$p,$cur-$p+1);
		$res[trim($regs[1])] = wha_extract_args($sub2,$sep);
		$chaine = str_replace($sub,"",$chaine);
	}
	//var_dump($res);
	$args = explode($sep,$chaine);
	//var_dump($args);
	foreach($args as $arg){
		if (preg_match(',^([^=]*)=(.*)$,',$arg,$regs)){
				$res[preg_replace(',^_ap_,','',trim($regs[1]))] = $regs[2];
		}
	}
	//var_dump($res);
	return $res;
}

function wha_HMACSign($stringToSign, $partnerId, $keyId, $versionId = 2) {
	$key = wha_secret_key($partnerId, $keyId);
	$hmac = wha_hmac($key,$stringToSign);
	if (!$hmac) {
		spip_log('HMACSign : sign: hmac digest returned is null','wha');
		return null;
	}
	$signedString = "h=$hmac;p=$partnerId;k=$keyId;v=$versionId:{".$stringToSign."}";
	return $signedString;
}
    
function wha_unsign($stringToVerify) {
	$sepPlace = strpos($stringToVerify,':');
	if (($sepPlace < 2) || (strlen($stringToVerify) < $sepPlace + 2)) {
		spip_log('unsign : invalid message format: ' . $stringToVerify,'wha');
		return null;
	}
	$hmacString = substr($stringToVerify,0, $sepPlace);
	$unsignedString = substr($stringToVerify,$sepPlace + 1);

	$ahmacString = explode(';',$hmacString);
	$hmac = "";
	$hmacTable = array();
	foreach($ahmacString as $arg) {
		$arg = explode('=',$arg);
		$hmacTable[$arg[0]] = $arg[1];
	}
	if (!isset($hmacTable['h'])) {
		spip_log('unsign : unsign: hmac could not be parsed:' . $hmacString,'wha');
		return null;
	}
	$partnerId = intval($hmacTable['p']);
	$keyId = intval($hmacTable['k']);
	$remoteVersionId = intval($hmacTable['v']);
	
	$key = "";
	$key = wha_secret_key($partnerId, $keyId);

	$unsignedString = substr(trim($unsignedString),1,-1);
	$digest = wha_hmac($key,$unsignedString);
	// hum... ?
	//if ($hmacTable['h']!=$digest) {
	if (!$h!=$digest) {
		spip_log(" unsign: message string has been changed / hmac does not match ". $hmac . " " . $digest,'wha');
		return null;
	}
	return array($unsignedString,$partnerId,$keyId);
}

function wha_achat_desc($id_transaction,$desc,$montant,$infos_marchant=array()){
	# $montant=0.01; // debug
	$microtime = '';
	if ($microtime = microtime()){
		list($microtime,$time) = explode(' ',$microtime);
	}
	else $time = time();
	$infos_marchant['ts'] = date('Y-m-d H:i:s',$time).($microtime?substr($microtime,1,4):"");
	$infos_marchant['_ap_id_transaction'] = $id_transaction;
	$infos_marchant['pId'] = $id_transaction;
	$infos_marchant['_ap_wha_desc2'] = 'current'; // traiter le process sans popup, en fenetre courante
	include_spip('inc/filtres');
	return array(
			'ps'=>2, // payement Service
			'amt'=> sprintf('%.2f',round($montant,2)), //montant
			'mp'=> $infos_marchant, //merchantproperties
			'pi' => $id_transaction,
			'pg'=>0,
			'mUrl'=>url_absolue('pos_init'), // merChantURL, ne gere pas les urls avec ? ...
			'pd' => $desc, // description produit
			'pc' => 'Image',
			'hr'=>1, // handle refund
			'cur'=>'EUR',
			'cl'=>'-1', // class tout public
			//'t_stmp' => date('Y-m-d H:i:s',$time+3000).($microtime?substr($microtime,1,4):""),
			);
}

function wha_abo_desc($aboId,$infos_marchant=array()){
	$microtime = '';
	if ($microtime = microtime()){
		list($microtime,$time) = explode(' ',$microtime);
	}
	else $time = time();
	$infos_marchant['ts'] = date('Y-m-d H:i:s',$time).($microtime?substr($microtime,1,4):"");
	//$infos_marchant['_ap_id_transaction'] = $id_transaction;
	$infos_marchant['oId'] = $aboId;
	$infos_marchant['cur'] = 'EUR';
	$infos_marchant['_ap_wha_desc2'] = 'current'; // traiter le process sans popup, en fenetre courante
	include_spip('inc/filtres');
	return array(
			'oid' => $aboId,
			'mp'=> $infos_marchant, //merchantproperties
			'mUrl'=>url_absolue('pos_bundle'), // merChantURL, ne gere pas les urls avec ? ...
			'amt'=> '0.0', //montant
			//'pd' => $desc, // description produit
			//'pc' => 'Image',
			//'hr'=>1, // handle refund
			//'cur'=>'EUR',
			//'cl'=>'-1', // class tout public
			//'t_stmp' => date('Y-m-d H:i:s',$time+3000).($microtime?substr($microtime,1,4):""),
			);
}

// $action = AuthorizeReq ou Confirm
function wha_action_url($action,$achat_desc,$partnerId,$keyId,$node=false,$versionId=2) {
	$url = $node?$node:_WHA_NODE_URL;
	$signed = wha_message($action,$achat_desc,$partnerId,$keyId,$versionId);
	return $url.((strpos($url,'?')===FALSE)?'?':'&').'m='.urlencode($signed);
}

function wha_message($action,$desc,$partnerId,$keyId,$versionId=2){
	$message = array('c'=>$action);
	if ($desc)
		$message['v'] = $desc;
	$stringToSign = wha_formate_args('',$message);
	$signed = wha_HMACSign($stringToSign,$partnerId,$keyId,$versionId);
	return $signed;
}

function wha_url_transaction($id_transaction,$transaction_hash,$partnerId,$keyId,$args = array(),$node=false){
	$res = sql_select("*","spip_transactions","id_transaction=".intval($id_transaction)." AND transaction_hash=".sql_quote($transaction_hash));
	if (!$row = spip_fetch_array($res))
		return "";
	if ($row['reglee']=='oui')
		return "";
	$achat_desc = wha_achat_desc($id_transaction,"Votre commande No $id_transaction",$row['montant'],$args);
	return parametre_url(wha_action_url("AuthorizeReq",$achat_desc,$partnerId,$keyId,$node),'lg','fr','&');
}

function wha_url_abonnement($aboId,$id_transaction,$partnerId,$keyId,$args = array(),$node=false){
	$abo_desc = wha_abo_desc($aboId,array_merge($args,array('_ap_id_transaction'=>$id_transaction)));
	$node = $node ? $node : _WHA_NODE_ABO_URL; // le node pour l'abo est different du node pour l'acte
	return parametre_url(wha_action_url("OfferAuthorizeReq",$abo_desc,$partnerId,$keyId,$node,3),'lg','fr','&');
}

function wha_url_confirm($trxId,$montant,$partnerId,$keyId,$node=false){
	# $montant=0.01; // debug
	$url = wha_action_url("Confirm",array(
	's_rate'=>0,
	'n_amt'=>$montant,
	'g_amt'=>$montant,
	'v_amt'=>$montant,
	'trxId'=>$trxId,
	'v_rate'=>0,
	's_amt'=>0,
	'cur'=>'EUR'
	),$partnerId,$keyId,$node);
	return $url;
}

function wha_url_confirm_abo($uoid,$partnerId,$keyId,$node=false){
	$url = wha_action_url("m_offerConfirm",array(
	'uoid'=>$uoid
	),$partnerId,$keyId,$node,3);
	return $url;
}


function wha_url_check_abo($uoid,$pid,$partnerId,$keyId,$node=false){
	$url = wha_action_url("m_consume",array(
	'pc'=>'pca',
	'uoid'=>$uoid,
	'pi'=>$pid,
	'desc'=>'desc',
	),$partnerId,$keyId,$node,3);
	return $url;
}

/**
 * Detecter le logo a afficher en fonction du FAI presume
 * @return bool|string
 */
function wha_logo_detecte_fai_visiteur(){
	$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ? $_SERVER['HTTP_X_FORWARDED_FOR'] : ($_SERVER['HTTP_CLIENT_IP']
			? $_SERVER['HTTP_CLIENT_IP'] : $_SERVER['REMOTE_ADDR']);
	$gethostbyaddr = gethostbyaddr($ip);
	$dyn = explode('.', $gethostbyaddr);
	$nombre_de_points = substr_count($gethostbyaddr, '.');
	$fai = $dyn[$nombre_de_points-1];
	if ($fai=="wanadoo")
		$fai = "orange";
	$fai_supporte = array("proxad", "orange", "bouygues");
	if (in_array($fai, $fai_supporte))
		return find_in_path("presta/internetplus/logo/$fai.png");
	else {
		#spip_log($fai."non supporte par internet+".$gethostbyaddr);
		return find_in_path("presta/internetplus/logo/logo_wha_abo_sans_sfr.png");
	}
}
