<?php

namespace OCA\OrganizationFolders\Command\Resource;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class CreateResourceFromTemplate extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:resources:create-from-template')
			->setDescription('Create a new resource in organization folder from a template')
			->addOption('organization-folder-id', null, InputOption::VALUE_REQUIRED, 'ID of organization folder to create resource in')
			->addOption('parent-resource-id', null, InputOption::VALUE_OPTIONAL, 'ID of parent resource (leave out if creating at top level in organization folder)')
			->addOption('template-provider-id', null, InputOption::VALUE_REQUIRED, 'ID of template provider')
			->addOption('template-id', null, InputOption::VALUE_REQUIRED, 'ID of template to create resource from');

		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$organizationFolderId = $input->getOption('organization-folder-id');
		$parentResourceId = $input->getOption('parent-resource-id');
		$providerId = $input->getOption('template-provider-id');
		$templateId = $input->getOption('template-id');

		try {
			$resource = $this->resourceTemplateService->createFromResourceTemplate(
				providerId: $providerId,
				templateId: $templateId,
				organizationFolderId: $organizationFolderId,
				parentResourceId: $parentResourceId,
			)["resource"];

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($resource)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
