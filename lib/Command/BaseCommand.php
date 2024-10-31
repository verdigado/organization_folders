<?php

namespace OCA\OrganizationFolders\Command;

use OC\Core\Command\Base;
use OCP\IDateTimeFormatter;

use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Interface\TableSerializable;

abstract class BaseCommand extends Base {

	public function __construct(
		private readonly IDateTimeFormatter $dateTimeFormatter,
        protected readonly OrganizationFolderService $organizationFolderService,
        protected ResourceService $resourceService,
		protected ResourceMemberService $resourceMemberService,
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
