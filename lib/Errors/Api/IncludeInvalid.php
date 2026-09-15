<?php

namespace OCA\OrganizationFolders\Errors\Api;

class IncludeInvalid extends ApiError {
	public function __construct(public readonly string $include) {
		parent::__construct(
			...$this->t("\"%s\" is not a valid include for this endpoint", [$include]),
		);
	}
}

