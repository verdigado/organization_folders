<?php

namespace OCA\OrganizationFolders\Errors\Api;

class InvalidResourceType extends ApiError {
	public function __construct(public readonly string $type) {
		parent::__construct($type . " is not a valid resource type");
	}
}
