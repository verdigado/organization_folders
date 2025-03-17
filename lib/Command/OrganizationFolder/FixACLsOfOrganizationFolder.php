<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolder;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class FixACLsOfOrganizationFolder extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:recreate-acls')
			->setDescription('Ensures all ACLs of organization folder are correctly set. Should not be neccessary to run unless ACL rules have been modified accidentally by an admin as ACLs will be created/modified automatically as resources are changed')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the organization folder');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (int)$input->getArgument('id');

		try {
			$output->writeln(var_dump($this->organizationFolderService->applyPermissions($id)));

			$output->writeln("done");

			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
