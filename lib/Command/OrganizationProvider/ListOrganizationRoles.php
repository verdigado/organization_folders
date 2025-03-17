<?php

namespace OCA\OrganizationFolders\Command\OrganizationProvider;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListOrganizationRoles extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:organization-roles:list')
			->setDescription('List all roles in a specific organization')
			->addArgument('provider-id', InputArgument::REQUIRED, 'provider to query')
			->addArgument('organization-id', InputArgument::REQUIRED, 'organization id to query roles of');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$providerId = $input->getArgument('provider-id');
			$organizationId = (int)$input->getArgument('organization-id');

			if(!$this->organizationProviderManager->hasOrganizationProvider($providerId)) {
				$output->writeln("<error>organization provider not found</error>");
				return 0;
			}

			$roles = $this->organizationProviderManager->getOrganizationProvider($providerId)->getRolesOfOrganization($organizationId);

			$this->writeTableInOutputFormat($input, $output, $this->formatTableSerializables($roles));
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
