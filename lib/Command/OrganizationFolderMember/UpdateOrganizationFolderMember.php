<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolderMember;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;

class UpdateOrganizationFolderMember extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:members:update')
			->setDescription('Update a member of an organization folder')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the organization folder member')
			->addOption('permission-level', null, InputOption::VALUE_REQUIRED, 'New permissions level of member (valid values: MEMBER, MANAGER, ADMIN)');
		
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (int)$input->getArgument('id');
		$permissionLevel = OrganizationFolderMemberPermissionLevel::fromNameOrValue($input->getOption('permission-level'));

		try {
			$member = $this->organizationFolderMemberService->update(
				id: $id,
				permissionLevel: $permissionLevel,
			);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($member)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
