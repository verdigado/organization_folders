<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolderMember;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;

class CreateOrganizationFolderMember extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:members:create')
			->setDescription('Create a new member of an organization folder')
			->addOption('organization-folder-id', null, InputOption::VALUE_REQUIRED, 'Id of organization folder to create member of')
			->addOption('permission-level', null, InputOption::VALUE_REQUIRED, 'Permissions level of member (valid values: MEMBER, MANAGER, ADMIN)')
			->addOption('principal-type', null, InputOption::VALUE_REQUIRED, 'Type of principal (valid values: USER, GROUP, ORGANIZATION_MEMBER, ORGANIZATION_ROLE')
			->addOption('principal-id', null, InputOption::VALUE_OPTIONAL, 'For type GROUP: "[group_name]", for type ORGANIZATION_MEMBER: "[organization_provider_id]:[organization_id]", for type ORGANIZATION_ROLE: "[organization_provider_id]:[role_id]"');
		
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$organizationFolderId = $input->getOption('organization-folder-id');
		$permissionLevel = OrganizationFolderMemberPermissionLevel::fromNameOrValue($input->getOption('permission-level'));
		$principalType = PrincipalType::fromNameOrValue($input->getOption('principal-type'));
		$principalId = $input->getOption('principal-id');

        $organizationFolder = $this->organizationFolderService->find($organizationFolderId);
		$principal = $this->principalFactory->buildPrincipal($principalType, $principalId);

		try {
			$member = $this->organizationFolderMemberService->create(
				organizationFolder: $organizationFolder,
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
