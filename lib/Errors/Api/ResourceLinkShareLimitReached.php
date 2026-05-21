<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Db\Resource;

class ResourceLinkShareLimitReached extends ApiError {
	public function __construct(
        public readonly Resource $resource,
        public readonly int $limit,
    ) {
		parent::__construct(
			...$this->t("Resource \"%s\" (ID: %s) only supports up to %s link shares", [
				$resource->getName(),
				$resource->getId(),
                $limit,
			])
		);
	}
}
