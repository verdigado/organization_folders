<?php

namespace OCA\OrganizationFolders\Errors;

class ResourceNameNotUnique extends \RuntimeException {
	public function __construct() {
        parent::__construct("Resource cannot have the same name as sibling resource");
	}
}
