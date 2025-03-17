<?php

namespace OCA\OrganizationFolders\Command\OrganizationProvider;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListOrganizations extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:organizations:list')
			->setDescription('List all organizations provided by a specific organization provider')
			->addArgument('provider-id', InputArgument::REQUIRED, 'provider to query')
			->addArgument('parent-organization-id', InputArgument::OPTIONAL, 'parent organization to fetch child organizations of. Using top-level if omitted');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$providerId = $input->getArgument('provider-id');

			if(ctype_digit($input->getArgument('parent-organization-id'))) {
				$parentOrganizationId = (int)$input->getArgument('parent-organization-id');
			} else {
				$parentOrganizationId = null;
			}
			

			if(!$this->organizationProviderManager->hasOrganizationProvider($providerId)) {
				$output->writeln("<error>organization provider not found</error>");
				return 0;
			}

			$organizations = $this->organizationProviderManager->getOrganizationProvider($providerId)->getSubOrganizations($parentOrganizationId);

			$this->writeTableInOutputFormat($input, $output, $this->formatTableSerializables($organizations));
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
