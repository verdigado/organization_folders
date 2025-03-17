<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolderMember;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListOrganizationFolderMembers extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:members:list')
			->addArgument('organization-folder-id', InputArgument::REQUIRED, 'Id of organization folder')
			->setDescription('List all members of organization folder.');
			
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$organizationFolderId = $input->getArgument('organization-folder-id');

			$members = $this->organizationFolderMemberService->findAll($organizationFolderId);

			$this->writeTableInOutputFormat($input, $output, $this->formatTableSerializables($members));
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
