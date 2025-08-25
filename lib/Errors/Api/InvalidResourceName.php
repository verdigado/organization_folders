<?php

namespace OCA\OrganizationFolders\Errors\Api;

class InvalidResourceName extends ApiError {
	public function __construct(public readonly string $name) {
		parent::__construct(
			...$this->t("\"%s\" is not a valid resource name", [$name]),
		);
	}
}
