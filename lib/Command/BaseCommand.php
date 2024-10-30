<?php

namespace OCA\OrganizationFolders\Command;

use OC\Core\Command\Base;
use OCP\IDateTimeFormatter;

use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Interface\TableSerializable;

abstract class BaseCommand extends Base {

	public function __construct(
		private readonly IDateTimeFormatter $dateTimeFormatter,
        protected readonly OrganizationFolderService $organizationFolderService,
        protected ResourceService $resourceService,
	) {
		parent::__construct();
	}

	protected function formatTableSerializable(TableSerializable $serializable): array {
		return $serializable->tableSerialize();
	}

    protected function formatOrganizationFolders(array $organizationFolders) {
		return array_map($this->formatTableSerializable(...), $organizationFolders);
	}

    protected function formatResources(array $resources): array {
		return array_map($this->formatTableSerializable(...), $resources);
	}
}
