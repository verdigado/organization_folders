<?php

namespace OCA\OrganizationFolders\Command\Resource;

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
			->addOption('parent-resource-id', null, InputOption::VALUE_REQUIRED, 'ID of parent resource (leave out if creating at top level in organization folder)')
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
			$result = $this->resourceTemplateService->createFromResourceTemplate(
				providerId: $providerId,
				templateId: $templateId,
				organizationFolderId: $organizationFolderId,
				parentResourceId: $parentResourceId,
			);

			if($input->getOption("output") === "plain") {
				$output->writeln("Created the following resource from template:");
				$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($result["resource"])]);
				$output->writeln("");
				$output->writeln("with the following members:");
				$this->writeTableInOutputFormat($input, $output, $this->formatTableSerializables($result["members"]));
			} else {
				$this->writeArrayInOutputFormat($input, $output, $result);
			}
			return 0;
		} catch (\Exception $e) {
			$this->handleException($output, $e);
			return 1;
		}
	}
}
