<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Command;

use OC\Core\Command\Base;

use OCP\IL10N;

use OCA\OrganizationFolders\Errors\Api\ApiError;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Service\ResourceTemplateService;
use OCA\OrganizationFolders\Service\PermissionsService;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Interface\TableSerializable;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Registry\ResourceTemplateProviderRegistry;

use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Base {

	public function __construct(
		protected readonly IL10N $l10n,
		protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly OrganizationFolderMemberService $organizationFolderMemberService,
		protected readonly ResourceService $resourceService,
		protected readonly ResourceMemberService $resourceMemberService,
		protected readonly ResourceTemplateService $resourceTemplateService,
		protected readonly PermissionsService $permissionsService,
		protected readonly OrganizationProviderManager $organizationProviderManager,
		protected readonly ResourceTemplateProviderRegistry $resourceTemplateProviderRegistry,
		protected readonly PrincipalFactory $principalFactory,
	) {
		parent::__construct();
	}

	protected function handleException(OutputInterface $output, \Exception $e, bool $trace = false) {
		if($e instanceof ApiError) {
			$log = "Exception " . get_class($e) . " \"{$e->getMessage()}\"";
			if($e->getDetails() !== null) {
				$log .= " with details " . json_encode($e->getDetails());
			}
			$log .= " at {$e->getFile()} line {$e->getLine()}";
		} else {
			$log = "Exception \"{$e->getMessage()}\" at {$e->getFile()} line {$e->getLine()}";
		}
		$output->writeln("<error>" . $log . "</error>");
		if($trace) {
			$output->writeln("<error>Trace:");
			$output->writeln($e->getTraceAsString() . "</error>");
		}
	}

	/**
	 * @param TableSerializable $serializable
	 * @param ?array $params
	 * @return array<string, string>
	 */
	protected function formatTableSerializable(TableSerializable $serializable, ?array $params = null): array {
		return $serializable->tableSerialize($this->l10n, $params);
	}

	/**
	 * @param TableSerializable[] $serializables
	 * @param ?array $params
	 * @return array<string, string>[]
	 */
	protected function formatTableSerializables(array $serializables, ?array $params = null): array {
		$result = [];
		foreach($serializables as $serializable) {
			$result[] = $serializable->tableSerialize($this->l10n, $params);
		}
		return $result;
	}

	/**
	 * Transforms "READ+WRITE" into {READ: true, WRITE: true}
	 * @param ?string $input
	 * @return ?array<string, bool>
	 */
	protected function parsePermissionsInput(?string $input): ?array {
		if(!isset($input)) {
			return null;
		}

		$result = [];

		$permissions = explode("+", $input);

		foreach($permissions as $permission) {
			if($permission !== "") {
				$result[strtoupper($permission)] = true;
			}
		}

		return $result;
	}
}
