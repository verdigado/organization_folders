<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolderMember;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class DeleteOrganizationFolderMember extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:members:delete')
			->setDescription('Delete a member of an organization folder')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the organization folder');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (int)$input->getArgument('id');

		try {
			$this->organizationFolderMemberService->delete($id);

            $output->writeln("done");

			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
