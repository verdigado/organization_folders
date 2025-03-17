<?php

namespace OCA\OrganizationFolders\Command\ResourceMember;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListResourceMembers extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:resource-members:list')
			->addArgument('resource-id', InputArgument::REQUIRED, 'Id of Resource')
			->setDescription('List all members of resource.');
			
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$resourceId = $input->getArgument('resource-id');

			$members = $this->resourceMemberService->findAll($resourceId);

			$this->writeTableInOutputFormat($input, $output, $this->formatTableSerializables($members));
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
