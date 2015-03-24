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
 * Determiner le mode test en fonction d'un define ou de la config
 * @param array $config
 * @return bool
 */
function cmcic_is_sandbox($config){
	$test = false;
	// _CMCIC_TEST force a TRUE pour utiliser l'adresse de test de CMCIC
	if ( (defined('_CMCIC_TEST') AND _CMCIC_TEST)
	  OR (isset($config['mode_test']) AND $config['mode_test']) ){
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

	// URL d'accès à la banque.
	// Par défaut, l'adresse CIC de paiement normal.
	switch($config['service']){
		case "CMUT":
			$host = "https://paiement.creditmutuel.fr";
			break;
		case "OBC":
			$host = "https://ssl.paiement.banque-obc.fr";
			break;
		case "CIC":
		default:
			$host = "https://ssl.paiement.cic-banques.fr";
			break;
	}

	if (cmcic_is_sandbox($config))
		$host .= "/test";
	return $host . "/" . _CMCIC_URLPAIEMENT;

}

/*****************************************************************************
 *
 * "open source" kit for CMCIC-P@iement(TM) 
 *
 * File "CMCIC_Tpe.inc.php":
 *
 * Author   : Euro-Information/e-Commerce (contact: centrecom@e-i.com)
 * Version  : 1.04
 * Date     : 01/01/2009
 *
 * Copyright: (c) 2009 Euro-Information. All rights reserved.
 * License  : see attached document "License.txt".
 *
 *****************************************************************************/

define("_CMCIC_CTLHMAC","V1.04.sha1.php--[CtlHmac%s%s]-%s");
define("_CMCIC_CTLHMACSTR", "CtlHmac%s%s");
define("_CMCIC_CGI2_RECEIPT","version=2\ncdr=%s");
define("_CMCIC_CGI2_MACOK","0");
define("_CMCIC_CGI2_MACNOTOK","1\n");
define("_CMCIC_CGI2_FIELDS", "%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*");
define("_CMCIC_CGI1_FIELDS", "%s*%s*%s%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s*%s");
define("_CMCIC_URLPAIEMENT", "paiement.cgi");


/*****************************************************************************
*
* Classe / Class : CMCIC_Tpe
*
*****************************************************************************/

class CMCIC_Tpe {


	public $sVersion;	// Version du TPE - TPE Version (Ex : 3.0)
	public $sNumero;	// Numero du TPE - TPE Number (Ex : 1234567)
	public $sCodeSociete;	// Code Societe - Company code (Ex : companyname)
	public $sLangue;	// Langue - Language (Ex : FR, DE, EN, ..)
	public $sUrlOK;		// Url de retour OK - Return URL OK
	public $sUrlKO;		// Url de retour KO - Return URL KO
	public $sUrlPaiement;	// Url du serveur de paiement - Payment Server URL (Ex : https://paiement.creditmutuel.fr/paiement.cgi)

	private $_sCle;		// La clé - The Key
	

	// ----------------------------------------------------------------------------
	//
	// Constructeur / Constructor
	//
	// ----------------------------------------------------------------------------
	
	function __construct($config, $sLangue = "FR") {

		// contrôle de l'existence des constantes de paramétrages.
		$aRequiredConstants = array('_CMCIC_VERSION');
		$this->_checkTpeParams($aRequiredConstants);

		$this->sVersion = _CMCIC_VERSION;
		$this->_sCle = $config['CLE'];
		$this->sNumero = $config['TPE'];
		$this->sUrlPaiement = cmcic_url_serveur($config);

		$this->sCodeSociete = $config['CODESOCIETE'];
		$this->sLangue = $sLangue;

		$this->sUrlOK = (defined('_CMCIC_URLOK')?_CMCIC_URLOK:"");
		$this->sUrlKO = (defined('_CMCIC_URLKO')?_CMCIC_URLKO:"");

	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : getCle
	//
	// Renvoie la clé du TPE / return the TPE Key
	//
	// ----------------------------------------------------------------------------

	public function getCle() {

		return $this->_sCle;
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : _checkTpeParams
	//
	// Contrôle l'existence des constantes d'initialisation du TPE
	// Check for the initialising constants of the TPE
	//
	// ----------------------------------------------------------------------------

	private function _checkTpeParams($aConstants) {

		for ($i = 0; $i < count($aConstants); $i++)
			if (!defined($aConstants[$i]))
				die ("Erreur paramètre " . $aConstants[$i] . " indéfini");
	}

}


/*****************************************************************************
*
* Classe / Class : CMCIC_Hmac
*
*****************************************************************************/

class CMCIC_Hmac {

	private $_sUsableKey;	// La clé du TPE en format opérationnel / The usable TPE key

	// ----------------------------------------------------------------------------
	//
	// Constructeur / Constructor
	//
	// ----------------------------------------------------------------------------

	function __construct($oTpe) {
		
		$this->_sUsableKey = $this->_getUsableKey($oTpe);
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : _getUsableKey
	//
	// Renvoie la clé dans un format utilisable par la certification hmac
	// Return the key to be used in the hmac function
	//
	// ----------------------------------------------------------------------------

	private function _getUsableKey($oTpe){

		$hexStrKey  = substr($oTpe->getCle(), 0, 38);
		$hexFinal   = "" . substr($oTpe->getCle(), 38, 2) . "00";
    
		$cca0=ord($hexFinal); 

		if ($cca0>70 && $cca0<97) 
			$hexStrKey .= chr($cca0-23) . substr($hexFinal, 1, 1);
		else { 
			if (substr($hexFinal, 1, 1)=="M") 
				$hexStrKey .= substr($hexFinal, 0, 1) . "0"; 
			else 
				$hexStrKey .= substr($hexFinal, 0, 2);
		}


		return pack("H*", $hexStrKey);
	}

	// ----------------------------------------------------------------------------
	//
	// Fonction / Function : computeHmac
	//
	// Renvoie le sceau HMAC d'une chaine de données
	// Return the HMAC for a data string
	//
	// ----------------------------------------------------------------------------

	public function computeHmac($sData) {

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
	// Implémentation RFC 2104 HMAC pour PHP >= 4.3.0 - Création d'un SHA1 HMAC.
	// Elimine l'installation de mhash pour le calcul d'un HMAC
	// Adaptée de la version MD5 de Lance Rushing.
	//
	// ----------------------------------------------------------------------------

	public function hmac_sha1 ($key, $data) {
		
		$length = 64; // block length for SHA1
		if (strlen($key) > $length) { $key = pack("H*",sha1($key)); }
		$key  = str_pad($key, $length, chr(0x00));
		$ipad = str_pad('', $length, chr(0x36));
		$opad = str_pad('', $length, chr(0x5c));
		$k_ipad = $key ^ $ipad ;
		$k_opad = $key ^ $opad;

		return sha1($k_opad  . pack("H*",sha1($k_ipad . $data)));
	}	

}

// ----------------------------------------------------------------------------
// function getMethode 
//
// IN: 
// OUT: Données soumises par GET ou POST / Data sent by GET or POST
// description: Renvoie le tableau des données / Send back the data array
// ----------------------------------------------------------------------------

function getMethode()
{
    if ($_SERVER["REQUEST_METHOD"] == "GET")  
        return $_GET; 

    if ($_SERVER["REQUEST_METHOD"] == "POST")
	return $_POST;

    die ('Invalid REQUEST_METHOD (not GET, not POST).');
}

// ----------------------------------------------------------------------------
// function HtmlEncode
//
// IN:  chaine a encoder / String to encode
// OUT: Chaine encodée / Encoded string
//
// Description: Encode special characters under HTML format
//                           ********************
//              Encodage des caractères spéciaux au format HTML
// ----------------------------------------------------------------------------
function HtmlEncode ($data)
{
    $SAFE_OUT_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890._-";
    $encoded_data = "";
    $result = "";
    for ($i=0; $i<strlen($data); $i++)
    {
        if (strchr($SAFE_OUT_CHARS, $data{$i})) {
            $result .= $data{$i};
        }
        else if (($var = bin2hex(substr($data,$i,1))) <= "7F"){
            $result .= "&#x" . $var . ";";
        }
        else
            $result .= $data{$i};
            
    }
    return $result;
}
?>
