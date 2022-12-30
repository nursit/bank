<?php
namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressHelper;

class BankRecurrencesWatch extends Command {
	protected function configure() {
		$this
			->setName('bank:recurrences:watch')
			->setDescription('Liste les paiements recurrents à gérer et renouvelle/termine les récurrences du jour')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		include_spip('inc/bank_recurrences');

		$this->io->title("Mise à jour des paiements récurrents");

		$actions = bank_recurrences_watch_lister_actions();
		$nb = count($actions);

		if ($nb > 0) {
			$this->io->text("$nb paiements récurrents à mettre à jour");

			foreach ($actions as $action) {
				$display = implode(' ', $action);
				$this->io->care($display);
				/**
				 * @uses bank_recurrence_terminer()
				 * @uses bank_recurrence_renouveler()
				 */
				$callback = array_shift($action);
				$res = call_user_func_array($callback, $action);
				if ($res === false) {
					$this->io->fail($display);
				}
				else {
					$this->io->check($display);
				}
			}

			$this->io->success("Tous les paiements récurrents ont été mis à jour");
		}
		else {
			$this->io->care("Rien à faire");
		}
		return self::SUCCESS;
	}

}
