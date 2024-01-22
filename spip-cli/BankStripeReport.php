<?php
namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Stripe\BalanceTransaction;
use Stripe\Payout;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BankStripeReport extends Command {

	protected $payments = [];
	protected $transactions_pending = [];

	protected function configure() {
		$this
			->setName('bank:stripe:report')
			->addOption(
				'from',
				null,
				InputOption::VALUE_OPTIONAL,
				'Date de début (début du mois précédent par défaut)',
				''
			)
			->addOption(
				'to',
				null,
				InputOption::VALUE_OPTIONAL,
				'Date de fin (fin du mois précédent par défaut)',
				''
			)
			->setDescription('Liste les paiements Stripe et les frais et reversements associés')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		include_spip('inc/bank');
		include_spip('presta/stripe/inc/stripe');

		$this->io->title("Rapport des paiements Stripe");

		$from = $input->getOption('from');
		if (!$from or !strtotime($from)) {
			$from = date('Y-m-01 00:00:00', strtotime('-1month', strtotime(date('Y-m-15 00:00:00'))));
		} else {
			$from = date('Y-m-d H:i:s', strtotime($from));
		}

		$to = $input->getOption('to');
		if (!$to || !strtotime($to)) {
			$to = strtotime($from);
			$to = strtotime('+1month', $to);
			$to = strtotime(date('Y-m-01 00:00:00', $to));
			$to = date('Y-m-d H:i:s', $to - 1);
		} else {
			$to = date('Y-m-d H:i:s', strtotime($to));
		}

		$this->io->care("Date debut : $from");
		$this->io->care("Date fin : $to");

		// toutes les transactions stripe entre ces 2 dates
		$transactions = sql_allfetsel("*", "spip_transactions", "statut='ok' AND mode like 'stripe%' AND date_paiement >=" . sql_quote($from). " AND date_paiement <= " . sql_quote($to), '', 'date_paiement');
		if ($transactions) {
			$modes = array_column($transactions, 'mode');
			$modes = array_unique($modes);
		} else {
			$modes = sql_allfetsel("mode", "spip_transactions", "statut='ok' AND mode like 'stripe%'", '', 'date_paiement DESC', '0,100');
			$modes = array_column($modes, 'mode');
			$modes = array_unique($modes);
		}

		foreach ($modes as $mode) {
			$config = bank_config($mode);
			stripe_init_api($config);
			$payouts = $this->listPayouts($from, $to);
			if (empty($payouts)) {
				$this->io->fail("Aucun reversement dans ce créneau de dates");
			}
			$this->appendPayouts(bank_config_id($config), $payouts);
		}

		if (!$this->dispatchTransactions($transactions)) {
			return self::FAILURE;
		}

		// toutes les transactions et les payments_id sont associés, on peut donc lister
		$this->doCSV($payouts, $from, $to);
		return self::SUCCESS;
	}

	protected function listPayouts($from, $to) {

		$payouts = Payout::all([
			# 'status' => 'paid',
			'created' => [
				'gte' => strtotime($from),
				'lte' => strtotime($to),
			],
		]);

		$this->io->care(sprintf('%s payouts', count($payouts)));

		foreach ($payouts as $payout) {
			$this->io->section((new \DateTime())->setTimestamp($payout->created)->format('Y-m-d'));
			$this->io->listing([
				sprintf('<info>Id:</info> %s', $payout->id),
				sprintf('<info>Amount:</info> %s', $this->format_amount($payout->amount, $payout->currency)),
				sprintf('<info>Status:</info> %s', $payout->status),
				sprintf('<info>Reconciliation status:</info> %s', $payout->reconciliation_status),
			]);

			if ($payout->reconciliation_status === 'completed') {
				$this->io->writeln('<comment>Transactions:</comment>');
				$this->io->writeln('');
				$transactions = BalanceTransaction::all([
					'payout' => $payout->id,
					'type' => 'charge',
				]);
				$payout->metadata['transactions'] = $transactions;
				$total_amount = 0;
				$total_net = 0;
				$total_fee = 0;
				foreach ($transactions as $transaction) {
					$this->io->text(sprintf('<comment>%s</comment>', $transaction->id));
					$this->io->listing([
						sprintf('<info>Description:</info> %s', $transaction->description),
						sprintf('<info>Type:</info> %s', $transaction->type),
						sprintf('<info>Status:</info> %s', $transaction->status),
						sprintf('<info>Date:</info> %s', (new \DateTime())->setTimestamp($transaction->created)->format('Y-m-d H:i:s')),
						sprintf('<info>Amount:</info> %s', $this->format_amount($transaction->amount, $transaction->currency)),
						sprintf('<info>Net:</info> %s', $this->format_amount($transaction->net, $transaction->currency)),
						sprintf('<info>Fee:</info> %s', $this->format_amount($transaction->fee, $transaction->currency)),
					]);
					$total_amount += $transaction->amount;
					$total_net += $transaction->net;
					$total_fee += $transaction->fee;
				}

				$this->io->text('<comment>Total</comment>');
				$this->io->listing([
					sprintf('<info>Amount:</info> %s', $this->format_amount($total_amount, $transaction->currency)),
					sprintf('<info>Net:</info> %s', $this->format_amount($total_net, $transaction->currency)),
					sprintf('<info>Fee:</info> %s', $this->format_amount($total_fee, $transaction->currency)),
				]);
				$payout->metadata['transactions_total_amount'] = $total_amount;
				$payout->metadata['transactions_total_net'] = $total_net;
				$payout->metadata['transactions_total_fee'] = $total_fee;
			}
		}

		return $payouts;
	}

	protected function appendPayouts($config_id, $payouts) {
		foreach ($payouts as $payout) {
			foreach ($payout->metadata['transactions'] as $transaction) {
				$this->payments[$transaction->id] = [
					'payout_id' => $payout->id,
					'bank_config_id' => $config_id,
					'stripe_transaction' => $transaction,
				];
			}
		}
	}

	/**
	 * Format amount like 12345 to '123.45 EUR'
	 *
	 * @param int $amount amount in cents
	 * @param string $currency
	 * @return string
	 */
	protected function format_amount($amount, $currency) {
		// virer le &nbsp; que mettait Bank
		return textebrut(bank_affiche_montant($amount / 100, $currency));
	}


	protected function dispatchTransactions($transactions) {
		foreach ($transactions as $transaction) {
			$mode = $transaction['mode'];
			$config = bank_config($mode);
			$bank_config_id = bank_config_id($config);
			$transaction_id = explode('/', $transaction['autorisation_id'])[0];
			$id_transaction = $transaction['id_transaction'];

			if (empty($transaction_id)) {
				$this->io->error("Pas de autorisation_id sur transaction #$id_transaction");
				return false;
			}

			if (!empty($this->payments[$transaction_id])) {
				if ($this->payments[$transaction_id]['bank_config_id'] !== $bank_config_id) {
					$this->io->fail("Conflit de presta ID Stripe pour pour la transaction #$id_transaction $transaction_id...");
				}
				$this->payments[$transaction_id]['transaction'] = $transaction;
			} else {
				$this->transactions_pending[] = $transaction;
			}
		}

		// verifier que tous les payments ont bien une transaction
		$add = [];
		foreach ($this->payments as $transaction_id => $payment) {
			if (empty($payment['transaction'])) {
				if ($transaction = sql_fetsel("*", "spip_transactions", "mode like 'stripe%' AND autorisation_id like " . sql_quote($transaction_id . '/%'))) {
					$add[] = $transaction;
				} else {
					$this->io->error("Pas de transaction pour le paiement $transaction_id");
					//return false;
				}
			}
		}
		if (!empty($add)) {
			$this->dispatchTransactions($add);
		}

		return true;
	}

	protected function doCSV($payouts, $from, $to) {
		$lines = [];
		foreach ($payouts as $payout) {

			foreach ($payout->metadata['transactions'] as $transaction) {
				$lines[] = $this->makeLine(
					$transaction->id,
					date('Y-m-d H:i:s', $payout->arrival_date),
					"{$payout->description} | {$payout->id}",
					$payout->status,
					bank_devise_info($payout['currency'])
				);
			}
		}

		foreach ($this->transactions_pending as $transaction) {
			$lines[] = $this->makeLine($transaction);
		}

		$options = [
			'entetes' => [
				'#',
				'date_paiement',
				'date_reversement',
				'Facture',
				'Nom',
				'H.T.',
				'T.T.C',
				'Frais (ttc)',
				'Reversé (ttc)',
				'Reversement',
				'Status',
			],
			'envoyer' => false
		];
		$exporter_csv = charger_fonction('exporter_csv', 'inc');
		$fichier = $exporter_csv("Reversements Stripe $from - $to", $lines, $options);

		$this->io->section("Export CSV");
		readfile($fichier);
	}

	protected function makeLine($transaction_ou_payment_id, $payout_date = '-', $payout_desc = '-', $payout_status = '-', $devise_info = null) {
		if (is_string($transaction_ou_payment_id)) {
			$payment_id = $transaction_ou_payment_id;
			$payment = $this->payments[$payment_id]['stripe_transaction'];
			$transaction = $this->payments[$payment_id]['transaction'];
		} else {
			$transaction = $transaction_ou_payment_id;
			$payment = null;
			$payout_status = 'versement à venir';
		}

		$facture_ref = '';
		if (function_exists("bank_stripe_expliquer_reference_transaction")) {
			$facture_ref = bank_stripe_expliquer_reference_transaction($transaction);
		} else {
			if (!empty($transaction['id_facture'])) {
				$facture_ref = sql_getfetsel('no_comptable', 'spip_factures', 'id_facture=' . intval($transaction['id_facture']));
			}
		}

		if (!empty($transaction['id_auteur'])) {
			$auteur = sql_fetsel('*', 'spip_auteurs', 'id_auteur='.intval($transaction['id_auteur']));
			$nom = $auteur['nom'];
		} else {
			$nom = $transaction['auteur'];
			if ($p = strpos($nom, "\n") or $p = stripos($nom, "<br")) {
				$nom = substr($nom, 0, $p);
			}
			$nom = strip_tags($nom);
		}
		if (is_null($devise_info)) {
			$devise_info = bank_devise_info($transaction['devise']);
		}

		$line = [
			$transaction['id_transaction'],
			$transaction['date_paiement'],
			$payout_date,
			$facture_ref,
			$nom,
			$transaction['montant_ht'],
			$transaction['montant'],
			$payment ? number_format($payment->fee / 100, 2, '.', '') : '-',
			$payment ? number_format($payment->net / 100, 2, '.', '') : '-',
			$payout_desc,
			$payout_status
		];

		return $line;
	}
}