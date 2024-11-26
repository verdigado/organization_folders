<?php

namespace OCA\OrganizationFolders\Command\ResourceMember;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;

class CreateResourceMember extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:resource-members:create')
			->setDescription('Create a new member of resource')
			->addOption('resource-id', null, InputOption::VALUE_REQUIRED, 'Id of resource to create member of')
			->addOption('permission-level', null, InputOption::VALUE_REQUIRED, 'Permissions level of member (valid values: MEMBER, MANAGER)')
			->addOption('principal-type', null, InputOption::VALUE_REQUIRED, 'Type of principal (valid values: USER, GROUP, ROLE)')
			->addOption('principal-id', null, InputOption::VALUE_OPTIONAL, 'For type user: "[user_id]", for group: "[group_name]", for role: "[organization_provider_id]:[role_id]"');
        
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$resourceId = $input->getOption('resource-id');
		$permissionLevel = ResourceMemberPermissionLevel::fromNameOrValue($input->getOption('permission-level'));
		$principalType = PrincipalType::fromNameOrValue($input->getOption('principal-type'));
		$principalId = $input->getOption('principal-id');

		$principal = $this->principalFactory->buildPrincipal($principalType, $principalId);

		try {
			$member = $this->resourceMemberService->create(
				resourceId: $resourceId,
				permissionLevel: $permissionLevel,
				principal: $principal,
			);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($member)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
