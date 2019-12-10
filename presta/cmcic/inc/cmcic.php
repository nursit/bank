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

include_spip('inc/bank');

/**
 * Determiner le mode test en fonction d'un define ou de la config
 * @param array $config
 * @return bool
 */
function cmcic_is_sandbox($config){
	$test = false;
	// _CMCIC_TEST force a TRUE pour utiliser l'adresse de test de CMCIC
	if ((defined('_CMCIC_TEST') AND _CMCIC_TEST)
		OR (isset($config['mode_test']) AND $config['mode_test'])){
		$test = true;
	}
	return $test;
}

/**
 * Determiner l'URL d'appel serveur en fonction de la config
 *
 * @param array $config
 * @return string
 */
function cmcic_url_serveur($config){

	// URL d'acces a la banque.
	// Par defaut, l'adresse Monetico de paiement normal.
	switch ($config['service']) {
		case "CMUT":
		case "OBC":
		case "CIC":
		default:
			$host = "https://p.monetico-services.com";
			break;
	}

	if (cmcic_is_sandbox($config)){
		$host .= "/test";
	}
	return $host . "/" . _MONETICOPAIEMENT_URLPAYMENT;

}

/**
 * Sort and concat fields for the hmac calcul
 * complete the context if mandatory fields are missing
 *
 * @param array $contexte
 * @return string
 */
function cmcic_concat_fields(&$contexte){
	// ASCII alphabetic order
	$keys = ["TPE", "contexte_commande", "date", "dateech1", "dateech2", "dateech3", "dateech4", "lgue", "mail",
		"montant", "montantech1", "montantech2", "montantech3", "montantech4", "nbrech", "reference", "societe",
		"texte-libre", "url_retour_err", "url_retour_ok", "version"];

	$values = [];
	foreach ($keys as $key){
		if (!isset($contexte[$key])){
			$contexte[$key] = '';
		}
		$values[] = "$key=" . $contexte[$key];
	}
	return implode('*', $values);
}

/**
 * Sort and concat response vars for the hmac calcul
 * @param array $vars
 * @param $oTpe
 * @return string
 */
function cmcic_concat_response_fields($vars, $oTpe){

	$anomalies = [];
	if (array_key_exists('TPE', $vars) and $vars["TPE"]!=$oTpe->sNumero){
		$anomalies[] = "TPE";
	}
	if (array_key_exists('version', $vars) and $vars["version"]!=$oTpe->sVersion){
		$anomalies[] = "version";
	}
	if (!array_key_exists('MAC', $vars)){
		$anomalies[] = "MAC";
	}
	// fields to exclude from the MAC computation
	$excludes = ['MAC', 'action'];
	foreach ($excludes as $exclude){
		if (array_key_exists($exclude, $vars)){
			unset($vars[$exclude]);
		}
	}

	if (count($anomalies)){
		return "anomaly_detected: " . implode(':', $anomalies);
	}

	// order by key is mandatory
	ksort($vars);
	// map entries to "key=value" to match the target format
	array_walk($vars, function (&$a, $b){
		$a = "$b=$a";
	});

	// join all entries using asterisk as separator
	return implode('*', $vars);
}

/*****************************************************************************
 *
 * "open source" kit for Monetico paiement(TM)
 *
 * File "MoneticoPaiement_Ept.inc.php":
 *
 * Author   : Euro-Information/e-Commerce
 * Version  : 4.0
 * Date      : 05/06/2014
 *
 * Copyright: (c) 2014 Euro-Information. All rights reserved.
 * License  : see attached document "License.txt".
 *
 *****************************************************************************/

define("_MONETICOPAIEMENT_VERSION", "3.0");
define("_MONETICOPAIEMENT_CTLHMAC", "V%s.sha1.php--[CtlHmac%s%s]-%s");
define("_MONETICOPAIEMENT_CTLHMACSTR", "CtlHmac%s%s");
define("_MONETICOPAIEMENT_PHASE2BACK_RECEIPT", "version=2\ncdr=%s");
define("_MONETICOPAIEMENT_PHASE2BACK_MACOK", "0");
define("_MONETICOPAIEMENT_PHASE2BACK_MACNOTOK", "1\n");
define("_MONETICOPAIEMENT_URLPAYMENT", "paiement.cgi");

/*****************************************************************************
 *
 * Classe / Class : MoneticoPaiement_Ept
 *
 *****************************************************************************/
class MoneticoPaiement_Ept {


	public $sVersion;  // Version du TPE - EPT Version (Ex : 3.0)
	public $sNumero;  // Numero du TPE - EPT Number (Ex : 1234567)
	public $sCodeSociete;  // Code Societe - Company code (Ex : companyname)
	public $sLangue;  // Langue - Language (Ex : FR, DE, EN, ..)
	public $sUrlOK;    // Url de retour OK - Return URL OK
	public $sUrlKO;    // Url de retour KO - Return URL KO
	public $sUrlPaiement;  // Url du serveur de paiement - Payment Server URL (Ex : https://p.monetico-services.com/paiement.cgi)

	private $_sCle;    // La cle - The Key

	public $isOK; // flag pour signaler que le TPE est OK ou bon

	// ----------------------------------------------------------------------------
	//
	// Constructeur / Constructor
	//
	// ----------------------------------------------------------------------------

	function __construct($config, $sLangue = "FR"){

		// controle de l'existence des constantes de parametrages.
		$aRequiredConstants = array('_MONETICOPAIEMENT_VERSION');
		$this->_checkEptParams($config, $aRequiredConstants);

		$this->sVersion = _MONETICOPAIEMENT_VERSION;
		$this->_sCle = $config['CLE'];
		$this->sNumero = $config['TPE'];
		$this->sUrlPaiement = cmcic_url_serveur($config);

		$this->sCodeSociete = $config['CODESOCIETE'];
		$this->sLangue = $sLangue;

		$this->sUrlOK = '';
		$this->sUrlKO = '';

	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : getCle
	//
	// Renvoie la cle du TPE / return the EPT Key
	//
	// ----------------------------------------------------------------------------

	public function getCle(){

		return $this->_sCle;
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : _checkEptParams
	//
	// Controle l'existence des constantes d'initialisation du TPE
	// Check for the initialising constants of the EPT
	//
	// ----------------------------------------------------------------------------

	private function _checkEptParams($config, $aConstants){

		$this->isOK = true;

		for ($i = 0; $i<count($aConstants); $i++){
			if (!defined($aConstants[$i])){
				spip_log("Erreur parametre " . $aConstants[$i] . " indefini", $config['presta'] . _LOG_ERREUR);
				$this->isOK = false;
			}
		}

	}

}

/*****************************************************************************
 *
 * Classe / Class : MoneticoPaiement_Hmac
 *
 *****************************************************************************/
class MoneticoPaiement_Hmac {

	private $_sUsableKey;  // La cle du TPE en format operationnel / The usable TPE key

	// ----------------------------------------------------------------------------
	//
	// Constructeur / Constructor
	//
	// ----------------------------------------------------------------------------

	function __construct($oEpt){

		$this->_sUsableKey = $this->_getUsableKey($oEpt);
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : _getUsableKey
	//
	// Renvoie la cle dans un format utilisable par la certification hmac
	// Return the key to be used in the hmac function
	//
	// ----------------------------------------------------------------------------

	private function _getUsableKey($oEpt){

		$hexStrKey = substr($oEpt->getCle(), 0, 38);
		$hexFinal = "" . substr($oEpt->getCle(), 38, 2) . "00";

		$cca0 = ord($hexFinal);

		if ($cca0>70 && $cca0<97){
			$hexStrKey .= chr($cca0-23) . substr($hexFinal, 1, 1);
		} else {
			if (substr($hexFinal, 1, 1)=="M"){
				$hexStrKey .= substr($hexFinal, 0, 1) . "0";
			} else {
				$hexStrKey .= substr($hexFinal, 0, 2);
			}
		}


		return pack("H*", $hexStrKey);
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : computeHmac
	//
	// Renvoie le sceau HMAC d'une chaine de donnees
	// Return the HMAC for a data string
	//
	// ----------------------------------------------------------------------------

	public function computeHmac($sData){

		return strtolower(hash_hmac("sha1", $sData, $this->_sUsableKey));

		// If you don't have PHP 5 >= 5.1.2 and PECL hash >= 1.1
		// you may use the hmac_sha1 function defined below
		//return strtolower($this->hmac_sha1($this->_sUsableKey, $sData));
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : hmac_sha1
	//
	// RFC 2104 HMAC implementation for PHP >= 4.3.0 - Creates a SHA1 HMAC.
	// Eliminates the need to install mhash to compute a HMAC
	// Adjusted from the md5 version by Lance Rushing .
	//
	// Implementation RFC 2104 HMAC pour PHP >= 4.3.0 - Creation d'un SHA1 HMAC.
	// Elimine l'installation de mhash pour le calcul d'un HMAC
	// Adaptee de la version MD5 de Lance Rushing.
	//
	// ----------------------------------------------------------------------------

	public function hmac_sha1($key, $data){

		$length = 64; // block length for SHA1
		if (strlen($key)>$length){
			$key = pack("H*", sha1($key));
		}
		$key = str_pad($key, $length, chr(0x00));
		$ipad = str_pad('', $length, chr(0x36));
		$opad = str_pad('', $length, chr(0x5c));
		$k_ipad = $key ^ $ipad;
		$k_opad = $key ^ $opad;

		return sha1($k_opad . pack("H*", sha1($k_ipad . $data)));
	}

}

// ----------------------------------------------------------------------------
// function getMethode
//
// IN:
// OUT: Donnees soumises par GET ou POST / Data sent by GET or POST
// description: Renvoie le tableau des donnees / Send back the data array
// ----------------------------------------------------------------------------

function getMethode(){
	if ($_SERVER["REQUEST_METHOD"]=="GET"){
		return $_GET;
	}

	if ($_SERVER["REQUEST_METHOD"]=="POST"){
		return $_POST;
	}

	die ('Invalid REQUEST_METHOD (not GET, not POST).');
}

// ----------------------------------------------------------------------------
// function HtmlEncode
//
// IN:  chaine a encoder / String to encode
// OUT: Chaine encodee / Encoded string
//
// Description: Encode special characters under HTML format
//                           ********************
//              Encodage des caracteres speciaux au format HTML
// ----------------------------------------------------------------------------
function HtmlEncode($data){
	$SAFE_OUT_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890._-";
	$encoded_data = "";
	$result = "";
	for ($i = 0; $i<strlen($data); $i++){
		if (strchr($SAFE_OUT_CHARS, $data{$i})){
			$result .= $data{$i};
		} else {
			if (($var = bin2hex(substr($data, $i, 1)))<="7F"){
				$result .= "&#x" . $var . ";";
			} else {
				$result .= $data{$i};
			}
		}

	}
	return $result;
}
