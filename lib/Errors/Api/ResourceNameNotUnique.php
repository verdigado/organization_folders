<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ResourceNameNotUnique extends ApiError {
	public function __construct() {
		parent::__construct("Resource cannot have the same name as sibling resource");
	}
}
