<?php

namespace OCA\OrganizationFolders\Command\Resource;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ImportCalendar extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:resources:import-calendar')
			->setDescription('Import existing calendar as calendar resource')
			->addOption('organization-folder-id', null, InputOption::VALUE_REQUIRED, 'ID of organization folder to create resource in')
			->addOption('parent-resource-id', null, InputOption::VALUE_OPTIONAL, 'ID of parent resource (leave out if creating at top level in organization folder)')
			->addOption('calendar-id', null, InputOption::VALUE_REQUIRED, 'ID of calendar')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of resource')
			->addOption('inherit-managers', null, InputOption::VALUE_REQUIRED, 'Wether managers of the parent level (parent resource or organization folder for top level resources) should have management permissions')
			->addOption('member-permissions', null, InputOption::VALUE_OPTIONAL, 'permissions for members of resource')
			->addOption('manager-permissions', null, InputOption::VALUE_OPTIONAL, 'permissions for managers of resource')
			->addOption('inherited-member-permissions', null, InputOption::VALUE_OPTIONAL, 'permissions for users with access to the resource level above (or organization in case resource is top-level)');
		
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$organizationFolder = $input->getOption('organization-folder-id');
		$parentResource = $input->getOption('parent-resource-id');
		$calendarId = $input->getOption('calendar-id');
		$name = $input->getOption('name');
		$inheritManagers = $input->getOption('inherit-managers') === true || $input->getOption('inherit-managers') === "true";
		$memberPermissionsBitfield = $input->getOption('member-permissions');
		$managerPermissionsBitfield = $input->getOption('manager-permissions');
		$inheritedMemberPermissionsBitfield = $input->getOption('inherited-member-permissions');

		try {
			$resource = $this->resourceService->create(
				type: "calendar",
				organizationFolderId: $organizationFolder,
				name: $name,
				parentResourceId: $parentResource,
				active: true,
				inheritManagers : $inheritManagers,
				memberPermissionsBitfield: $memberPermissionsBitfield,
				managerPermissionsBitfield: $managerPermissionsBitfield,
				inheritedMemberPermissionsBitfield: $inheritedMemberPermissionsBitfield,

				alreadyExists: true,
				existingCalendarId: $calendarId,
			);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($resource)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
