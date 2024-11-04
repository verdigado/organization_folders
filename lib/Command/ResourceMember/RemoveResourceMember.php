<?php

namespace OCA\OrganizationFolders\Command\ResourceMember;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class RemoveResourceMember extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:remove-resource-member')
			->setDescription('Remove a member of a resource')
			->addArgument('id', InputArgument::REQUIRED, 'Id of the resource member');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (int)$input->getArgument('id');

		try {
			$this->resourceMemberService->delete($id);

            $output->writeln("done");

			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
