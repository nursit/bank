<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * (c) 2012-2020 - Distribue sous licence GNU/GPL
 *
 */

if (!defined('_ECRIRE_INC_VERSION')){
	return;
}


function presta_cheque_lister_devises_dist($config) {
	// Si ce mode par chèque a une config de devise, c'est uniquement ça qui est accepté
	if (isset($config['devise']) and $config['devise']) {
		return array($config['devise']);
	}
	
	return true;
}
