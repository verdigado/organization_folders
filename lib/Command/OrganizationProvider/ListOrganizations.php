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
			->setDescription('For a specific organization provider lists top-level organizations or direct sub-organizations of a given parent organization.')
			->addArgument('provider-id', InputArgument::REQUIRED, 'provider to query')
			->addArgument('parent-organization-id', InputArgument::OPTIONAL, 'parent organization to fetch sub-organizations of. Top-level organizations if omitted');
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
				return 1;
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
