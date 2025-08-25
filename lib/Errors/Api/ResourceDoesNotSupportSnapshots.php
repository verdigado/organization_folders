<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Db\Resource;

class ResourceDoesNotSupportSnapshots extends ApiError {
	public function __construct(
        public readonly Resource $resource,
    ) {
		parent::__construct(
			...$this->t("Resource \"%s\" (id: %s) does not support restoring from snapshots", [
				$resource->getName(),
				$resource->getId(),
			])
		);
	}
}
