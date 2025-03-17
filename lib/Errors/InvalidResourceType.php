<?php

namespace OCA\OrganizationFolders\Errors;

class InvalidResourceType extends \RuntimeException {
	public function __construct(string $type) {
		parent::__construct($type . " is not a valid resource type");
	}
}
