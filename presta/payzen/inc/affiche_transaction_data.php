<?php

function presta_payzen_inc_affiche_transaction_data_dist($data, $row) {

	$out = "";
	if (!empty($data['uuid'])) {
		$uuid = $data['uuid'];
		$out .= <<<champ
<div class="champ uuid contenu_uuid">
	<div class='label'>UUID</div>
	<div class='valeur'><tt>$uuid</tt></div>
</div>
champ;

	}

	if (!empty($data['transactions'])) {
		$table = [];
		foreach ($data['transactions'] as $info) {
			$table[] = [
				'uuid' => '<abbr title="' . $info["uuid"] . '"><tt>' . substr($info["uuid"],0,8) . '</tt></abbr>',
				'method' => $info["paymentMethodType"],
				'status' => $info["detailedStatus"] . ' + ' . $info["status"],
				'type' => $info["operationType"],
				'amount' => bank_affiche_montant(round($info["amount"]/100, 2), $info["currency"], true, true),
				'date' => affdate_heure($info["creationDate"]),
			];
		}
		$keys = array_keys($table[0]);
		$out .=
			'<div style="margin-top:var(--spip-margin-bottom);margin-left:calc(-1 * var(--spip-box-spacing-x));margin-right:calc(-1 * var(--spip-box-spacing-x))">'
			. '<table class="spip" style="width:100%;max-width: none;">'
			. '<thead><tr class="row_first"><th>' . implode('</th><th>', $keys) . '</th></tr></thead>'
			. '<tbody>'
		;
		foreach ($table as $row) {
			$out .= '<tr><td>' . implode('</td><td>', $row) . '</td></tr>';
		}
		$out .= '</tbody></table></div>';
	}

	return $out;
}
