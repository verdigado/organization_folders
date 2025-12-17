<?php

namespace OCA\OrganizationFolders\Command\ResourceTemplate;

use OCP\DB\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use OCA\OrganizationFolders\Command\BaseCommand;

class ListResourceTemplates extends BaseCommand {
	protected function configure(): void {
		$this
			->setName('organization-folders:resource-templates:list')
			->setDescription('List all resource templates');
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

			foreach($providers as $providerId => $provider) {
				$templates = $provider->getAllTemplates();

				foreach($templates as $template) {
					$result[] = [
						"Provider ID" => $providerId,
						"ID" => $template->getId(),
						"Class" => $template::class,
						"Description" => $template->getDescription(),
					];
				}
			}

			if(count($result) === 0 && $input->getOption('output') === self::OUTPUT_FORMAT_PLAIN) {
				$output->writeln("There are currently no resource templates.");
				return 0;
			}

			$this->writeTableInOutputFormat($input, $output, $result);
			return 0;
		} catch (Exception $e) {
			$output->writeln("<error>Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}</error>");
			return 1;
		}
	}
}
