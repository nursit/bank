<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function presta_cheque_lister_devises_dist($config) {
	// Si ce mode par chèque a une config de devise, c'est uniquement ça qui est accepté
	if ($config['devise']) {
		return array($config['devise']);
	}
	
	return true;
}
