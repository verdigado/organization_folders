<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Db\Resource;

class ResourceCannotBeItsOwnParent extends ApiError {
	public function __construct(public readonly Resource $resource) {
		parent::__construct("A resource cannot be its own parent");
	}
}
