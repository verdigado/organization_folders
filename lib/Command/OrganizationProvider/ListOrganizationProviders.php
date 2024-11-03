<?php

namespace OCA\OrganizationFolders\Command\OrganizationProvider;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListOrganizationProviders extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:list-organization-providers')
			->setDescription('List all registered organization providers');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$organizationProviders = $this->organizationProviderManager->getOrganizationProviders();

            $result = [];

            foreach($organizationProviders as $id => $organizationProvider) {
                $result[] = [
                    "Id" => $id,
                    "Class" => $organizationProvider::class,
                ];
            }

			$this->writeTableInOutputFormat($input, $output, $result);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
