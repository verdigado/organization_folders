<?php

namespace OCA\OrganizationFolders\Command\ResourceTemplate;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListResourceTemplateProviders extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:resource-template-providers:list')
			->setDescription('List all registered resource template providers');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$providers = $this->resourceTemplateProviderRegistry->getResourceTemplateProviders();

			if(count($providers) === 0 && $input->getOption('output') === self::OUTPUT_FORMAT_PLAIN) {
				$output->writeln("No resource template providers are registered.");
				return 0;
			}

			$result = [];

			foreach($providers as $id => $provider) {
				$result[] = [
					"Id" => $id,
					"Class" => $provider::class,
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
