<?php

namespace OCA\OrganizationFolders\Command\OrganizationProvider;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class GetOrganizationRole extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:organization-roles:get')
			->setDescription('Get a specific organization role by id')
			->addArgument('provider-id', InputArgument::REQUIRED, 'provider to query')
			->addArgument('role-id', InputArgument::REQUIRED, '');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$providerId = $input->getArgument('provider-id');
			$roleId = $input->getArgument('role-id');

			if(!$this->organizationProviderManager->hasOrganizationProvider($providerId)) {
				$output->writeln("<error>organization provider not found</error>");
				return 1;
			}

			$role = $this->organizationProviderManager->getOrganizationProvider($providerId)->getRole($roleId);

			$this->writeTableInOutputFormat($input, $output, [$this->formatTableSerializable($role)]);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
