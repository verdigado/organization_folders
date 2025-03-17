<?php

namespace OCA\OrganizationFolders\Command\Resource;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class DeleteResource extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:resources:delete')
			->setDescription('Delete a resource')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the resource');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (int)$input->getArgument('id');

		try {
			$this->resourceService->deleteById($id);

			$output->writeln("done");

			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
