<?php

namespace OCA\OrganizationFolders\Errors;

use OCA\OrganizationFolders\Db\Resource;

class ResourceDoesNotSupportSnapshots extends \RuntimeException {
	public function __construct(
        public readonly Resource $resource,
    ) {
		parent::__construct(
            message: "Resource \"" . $resource->getName() . " does not support restoring from snapshots",
        );
	}
}
