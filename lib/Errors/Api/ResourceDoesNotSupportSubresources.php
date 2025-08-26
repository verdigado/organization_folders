<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Db\Resource;

class ResourceDoesNotSupportSubresources extends ApiError {
	public function __construct(
        public readonly Resource $resource,
    ) {
		parent::__construct(
			...$this->t("Resource \"%s\" (ID: %s) does not support subresources", [
				$resource->getName(),
				$resource->getId(),
			])
		);
	}
}
