<?php

namespace OCA\OrganizationFolders\Command\Resource;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class UpdateResource extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:update-resource')
			->setDescription('Update a resource')
            ->addArgument('id', InputArgument::REQUIRED, 'Id of the resource to update')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'New name of resource');

		// folder type options
		$this
			->addOption('members-acl-permission', null, InputOption::VALUE_OPTIONAL, 'New acl permissions for members of resource')
			->addOption('managers-acl-permission', null, InputOption::VALUE_OPTIONAL, 'New acl permissions for managers of resource')
			->addOption('inherited-acl-permission', null, InputOption::VALUE_OPTIONAL, 'New acl permissions for users with access to the resource level above (or organization in case resource is top-level)');
		
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = $input->getArgument('id');
		$name = $input->getOption('name');

		$membersAclPermission = $input->getOption('members-acl-permission');
		$managersAclPermission = $input->getOption('managers-acl-permission');
		$inheritedAclPermission = $input->getOption('inherited-acl-permission');

		try {
			$resource = $this->resourceService->update(
                id: $id,
				name: $name,

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
