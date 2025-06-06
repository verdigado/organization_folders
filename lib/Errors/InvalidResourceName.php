<?php

namespace OCA\OrganizationFolders\Errors;

class InvalidResourceName extends \RuntimeException {
	public function __construct(public readonly string $name) {
		parent::__construct("\"" . $name . "\" is not a valid resource name");
	}
}
