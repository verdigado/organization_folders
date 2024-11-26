<?php

namespace OCA\OrganizationFolders\Command;

use OC\Core\Command\Base;
use OCP\IDateTimeFormatter;

use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Interface\TableSerializable;
use OCA\OrganizationFolders\Model\PrincipalFactory;

abstract class BaseCommand extends Base {

	public function __construct(
		private readonly IDateTimeFormatter $dateTimeFormatter,
        protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly OrganizationFolderMemberService $organizationFolderMemberService,
        protected readonly ResourceService $resourceService,
		protected readonly ResourceMemberService $resourceMemberService,
		protected readonly OrganizationProviderManager $organizationProviderManager,
		protected readonly PrincipalFactory $principalFactory,
	) {
		parent::__construct();
	}

	protected function formatTableSerializable(TableSerializable $serializable, ?array $params = null): array {
		return $serializable->tableSerialize($params);
	}

    protected function formatTableSerializables(array $serializables, ?array $params = null): array {
		$result = [];
		foreach($serializables as $serializable) {
			$result[] = $serializable->tableSerialize($params);
		}
		return $result;
	}
}
