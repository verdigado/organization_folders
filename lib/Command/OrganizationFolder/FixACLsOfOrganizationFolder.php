<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolder;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class FixACLsOfOrganizationFolder extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:recreate-acls')
			->setDescription('Ensures all ACLs of organization folder are correctly set. Should not be neccessary to run unless ACL rules have been modified accidentally by an admin as ACLs will be created/modified automatically as resources are changed')
			->addArgument('id', InputArgument::OPTIONAL, 'Id of the organization folder')
			->addOption('all', null, InputOption::VALUE_NONE, 'Run for all organization folders instead of specific id');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$all = $input->getOption('all');
		$id = $input->getArgument('id');
		
		try {
			if($all) {
				$organizationFolders = $this->organizationFolderService->findAll();

				foreach($organizationFolders as $organizationFolder) {
					$output->write("Applying permissions for organization folder \"" . $organizationFolder->getName() . "\" (id: " . $organizationFolder->getId() . ")... ");
					$this->organizationFolderService->applyAllPermissions($organizationFolder);
					$output->writeln("done");
				}

				return 0;
			} else {
				if(isset($id)) {
					$id = (int)$id;

					$this->organizationFolderService->applyAllPermissionsById($id);

					$output->writeln("done");

					return 0;
				} else {
					$output->writeln("Error: Must provide id or --all");
					return 1;
				}
			}
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
