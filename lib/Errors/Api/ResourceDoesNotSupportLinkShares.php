<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Db\Resource;

class ResourceDoesNotSupportLinkShares extends ApiError {
	public function __construct(
        public readonly Resource $resource,
    ) {
		parent::__construct(
			...$this->t("Resource \"%s\" (ID: %s) does not support link shares", [
				$resource->getName(),
				$resource->getId(),
			])
		);
	}
}
