<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolder;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class CreateOrganizationFolder extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:create')
			->setDescription('Create a new organization folder')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of the new organization folder')
			->addOption('quota', null, InputOption::VALUE_REQUIRED, 'Storage Quota of the new organization folder');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getOption('name');
		$quota = $input->getOption('quota');

		try {
			$organizationFolder = $this->organizationFolderService->create($name, $quota);

			$output->writeln(json_encode($organizationFolder));
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
