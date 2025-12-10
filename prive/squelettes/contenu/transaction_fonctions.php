<?php

function bank_affiche_transaction_data($id_transaction, $json) {
	if (empty($json)) {
		return '';
	}
	$data = json_decode($json, true);
	if ($data === null) {
		return "JSON mal formé : <pre>$json</pre>";
	}

	$row = sql_fetsel('*', 'spip_transactions', 'id_transaction = ' . intval($id_transaction));
	$presta = explode('/', $row['mode'])[0];

	include_spip("presta/$presta/inc/$presta");
	$affiche_transaction_data = charger_fonction("affiche_transaction_data", "presta/$presta/inc", true);
	if ($affiche_transaction_data) {
		return $affiche_transaction_data($data, $row);
	}

	return "JSON : <pre>$json</pre>";
}
