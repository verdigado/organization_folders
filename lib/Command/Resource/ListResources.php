<?php

namespace OCA\OrganizationFolders\Command\Resource;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListResources extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:list-resources')
            ->addArgument('organization-folder-id', InputArgument::REQUIRED, 'Id of Organization Folder')
            ->addArgument('parent-resource-id', InputArgument::OPTIONAL, 'Id of Organization Folder')
			->setDescription('List all resource in organization folder. Only shows one layer of tree at once, provide resource parent id to reveal child resources.');
            
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
            $organizationFolderId = $input->getArgument('organization-folder-id');
            $parentResourceId = $input->getArgument('parent-resource-id');

			$resources = $this->resourceService->findAll($organizationFolderId, $parentResourceId);

			$this->writeTableInOutputFormat($input, $output, $this->formatResources($resources));
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
