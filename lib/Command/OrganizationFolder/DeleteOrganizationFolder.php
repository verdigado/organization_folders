<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolder;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class DeleteOrganizationFolder extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:delete')
			->setDescription('Delete an organization folder')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the organization folder to remove');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (int)$input->getArgument('id');

		try {
			$this->organizationFolderService->remove($id);

            $output->writeln("done");

			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
