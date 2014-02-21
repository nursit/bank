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

/* Paybox ------------------------------------------------------------------ */

/**
 * Constantes pour paybox
 * plateforme de test
 * 
 */
if (!defined('_PAYBOX_URL'))
	define('_PAYBOX_URL',"https://tpeweb.paybox.com/cgi/MYchoix_pagepaiement.cgi");
if (!defined('_PAYBOX_URL_RESIL'))
	define('_PAYBOX_URL_RESIL',"https://tpeweb.paybox.com/cgi-bin/ResAbon.cgi");

/* ------------------------------------------------------------------------- */


//_PAYBOX_DIRECT_CLE
?>