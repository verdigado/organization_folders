<?php

namespace OCA\OrganizationFolders\Command\OrganizationProvider;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class GetOrganization extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:organizations:get')
			->setDescription('Get a specific organization by id')
			->addArgument('provider-id', InputArgument::REQUIRED, 'provider to query')
			->addArgument('organization-id', InputArgument::REQUIRED, '');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$providerId = $input->getArgument('provider-id');
			$organizationId = (int)$input->getArgument('organization-id');

			if(!$this->organizationProviderManager->hasOrganizationProvider($providerId)) {
				$output->writeln("<error>organization provider not found</error>");
				return 1;
			}

			$organization = $this->organizationProviderManager->getOrganizationProvider($providerId)->getOrganization($organizationId);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($organization)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
