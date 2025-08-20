<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Db\Resource;

class ResourceDoesNotSupportSubresources extends ApiError {
	public function __construct(
        public readonly Resource $resource,
    ) {
		parent::__construct(
            message: "Resource \"" . $resource->getName() . "\" does not support subresources",
        );
	}
}
