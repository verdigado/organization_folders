<?php

namespace OCA\OrganizationFolders\Command\OrganizationProvider;

use OCA\OrganizationFolders\OrganizationProvider\IListAllOrganizationsOfProvider;
use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListAllOrganizations extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:organizations:list-all')
			->setDescription('List all organizations of a specific organization provider')
			->addArgument('provider-id', InputArgument::REQUIRED, 'provider to query');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$providerId = $input->getArgument('provider-id');

			if(!$this->organizationProviderManager->hasOrganizationProvider($providerId)) {
				$output->writeln("<error>organization provider not found</error>");
				return 1;
			}

			$provider = $this->organizationProviderManager->getOrganizationProvider($providerId);

			if($provider instanceof IListAllOrganizationsOfProvider) {
				$organizations = $provider->getAllOrganizations();

				$this->writeTableInOutputFormat($input, $output, $this->formatTableSerializables($organizations));
				return 0;
			} else {
				$output->writeln("<error>this organization provider does not have the capability to fetch all organizations at once. Use occ organization-folders:organizations:list</error>");
				return 1;
			}
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
