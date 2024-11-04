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
			->addOption('quota', null, InputOption::VALUE_REQUIRED, 'Storage Quota of the new organization folder')
			->addOption('organization-provider', null, InputOption::VALUE_OPTIONAL, 'Organization provider of the organization this folder is part of')
			->addOption('organization-id', null, InputOption::VALUE_OPTIONAL, 'Organization id of the organization this folder is part of');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getOption('name');
		$quota = $input->getOption('quota');
		$organizationProviderId = $input->getOption('organization-provider');
		$organizationId = $input->getOption('organization-id');

		try {
			$organizationFolder = $this->organizationFolderService->create(
				name: $name,
				quota: $quota,
				organizationProvider: $organizationProviderId,
				organizationId: $organizationId,
			);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($organizationFolder)]);			
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
