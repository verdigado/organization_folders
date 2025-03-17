<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolder;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class GetOrganizationFolder extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:get')
			->setDescription('Get organization folder by id')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the organization folder to get');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (int)$input->getArgument('id');

		try {
			$organizationFolder = $this->organizationFolderService->find($id);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($organizationFolder)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
