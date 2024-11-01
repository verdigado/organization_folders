<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolder;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListOrganizationFolders extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:list')
			->setDescription('List all organization folders');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$organizationFolderGroupfolders = $this->organizationFolderService->findAll();

			$this->writeTableInOutputFormat($input, $output, $this->formatTableSerializables($organizationFolderGroupfolders));
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
