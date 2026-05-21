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
			->setDescription('Import existing calendar of service account as calendar resource')
			->addOption('organization-folder-id', null, InputOption::VALUE_REQUIRED, 'ID of organization folder to create resource in')
			->addOption('parent-resource-id', null, InputOption::VALUE_REQUIRED, 'ID of parent resource (leave out if creating at top level in organization folder)')
			->addOption('calendar-id', null, InputOption::VALUE_REQUIRED, 'ID of calendar (from oc_calendars database table)')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of resource')
			->addOption('inherit-managers', null, InputOption::VALUE_OPTIONAL, 'Whether managers of the parent level (parent resource or organization folder for top level resources) should have management permissions. Valid values: true, false', 'false')
			->addOption('member-permissions', null, InputOption::VALUE_REQUIRED, 'Permissions for members of resource. Valid values: Any selection of [READ, UPDATE, CREATE, DELETE, SHARE] for type=folder or [READ, UPDATE] for type=calendar seperated by +. Example: "READ+UPDATE"', '')
			->addOption('manager-permissions', null, InputOption::VALUE_REQUIRED, 'Permissions for managers of resource. Valid values: see --member-permissions', '')
			->addOption('inherited-member-permissions', null, InputOption::VALUE_REQUIRED, 'Permissions for users with access to the resource level above (or organization in case resource is top-level). Valid values: see --member-permissions', '')
			->addOption('template-provider-id', null, InputOption::VALUE_REQUIRED, 'Option for experts. Marks resource as created from a specific resource template. Does not actually apply template.')
			->addOption('template-id', null, InputOption::VALUE_REQUIRED, '');
		
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$organizationFolder = $input->getOption('organization-folder-id');
		$parentResource = $input->getOption('parent-resource-id');
		$calendarId = $input->getOption('calendar-id');
		$name = $input->getOption('name');
		// true if used without value or used with value "true"
		$inheritManagers = is_null($input->getOption('inherit-managers')) || $input->getOption('inherit-managers') === "true";
		$memberPermissions = $this->parsePermissionsInput($input->getOption('member-permissions'));
		$managerPermissions = $this->parsePermissionsInput($input->getOption('manager-permissions'));
		$inheritedMemberPermissions = $this->parsePermissionsInput($input->getOption('inherited-member-permissions'));
		$templateProviderId = $input->getOption('template-provider-id');
		$templateId = $input->getOption('template-id');

		if(isset($templateProviderId) && isset($templateId) && $templateProviderId !== "" && $templateId !== "") {
			$createdFromTemplateId = $templateProviderId . ":" . $templateId;
		} else {
			$createdFromTemplateId = null;
		}

		try {
			$resource = $this->resourceService->create(
				type: "calendar",
				organizationFolderId: $organizationFolder,
				name: $name,
				parentResourceId: $parentResource,
				active: true,
				inheritManagers : $inheritManagers,
				memberPermissions: $memberPermissions,
				managerPermissions: $managerPermissions,
				inheritedMemberPermissions: $inheritedMemberPermissions,
				createdFromTemplateId: $createdFromTemplateId,

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
