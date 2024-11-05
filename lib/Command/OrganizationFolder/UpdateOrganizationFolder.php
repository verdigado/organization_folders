<?php

namespace OCA\OrganizationFolders\Command\OrganizationFolder;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class UpdateOrganizationFolder extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:update')
			->setDescription('Update an organization folder')
            ->addArgument('id', InputArgument::REQUIRED, 'Id of the organization folder to update')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'New name of the organization folder')
			->addOption('quota', null, InputOption::VALUE_OPTIONAL, 'New storage quota of the organization folder')
			->addOption('organization-provider', null, InputOption::VALUE_OPTIONAL, 'New organization provider of the organization this folder will be part of')
			->addOption('organization-id', null, InputOption::VALUE_OPTIONAL, 'New Organization id of the organization this folder will be part of');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
        $id = (int)$input->getArgument('id');
		$name = $input->getOption('name');
		$quota = $input->getOption('quota');
		$organizationProviderId = $input->getOption('organization-provider');

        if(ctype_digit($input->getOption('organization-id'))) {
            $organizationId = (int)$input->getOption('organization-id');
        }

		try {
			$organizationFolder = $this->organizationFolderService->update(
                id: $id,
				name: $name,
				quota: $quota,
				organizationProviderId: $organizationProviderId,
				organizationId: $organizationId,
			);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($organizationFolder)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
