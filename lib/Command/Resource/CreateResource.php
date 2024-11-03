<?php

namespace OCA\OrganizationFolders\Command\Resource;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class CreateResource extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:create-resource')
			->setDescription('Create a new resource in organization folder')
			->addOption('organization-folder', null, InputOption::VALUE_REQUIRED, 'Id of organization folder to create resource in')
			->addOption('type', null, InputOption::VALUE_REQUIRED, 'Type of resource (valid values: folder)')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of resource')
			->addOption('parent-resource', null, InputOption::VALUE_OPTIONAL, 'Id of parent resource (leave out if creating at top level in organization folder)');

		// folder type options
		$this
			->addOption('members-acl-permission', null, InputOption::VALUE_OPTIONAL, 'acl permissions for members of resource')
			->addOption('managers-acl-permission', null, InputOption::VALUE_OPTIONAL, 'acl permissions for managers of resource')
			->addOption('inherited-acl-permission', null, InputOption::VALUE_OPTIONAL, 'acl permissions for users with access to the resource level above (or organization in case resource is top-level)');
		
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$organizationFolder = $input->getOption('organization-folder');
		$type = $input->getOption('type');
		$name = $input->getOption('name');
		$parentResource = $input->getOption('parent-resource');

		$membersAclPermission = $input->getOption('members-acl-permission');
		$managersAclPermission = $input->getOption('managers-acl-permission');
		$inheritedAclPermission = $input->getOption('inherited-acl-permission');

		try {
			$resource = $this->resourceService->create(
				type: $type,
				organizationFolderId: $organizationFolder,
				name: $name,
				parentResourceId: $parentResource,

				membersAclPermission: $membersAclPermission,
				managersAclPermission: $managersAclPermission,
				inheritedAclPermission: $inheritedAclPermission,
			);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($resource)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
