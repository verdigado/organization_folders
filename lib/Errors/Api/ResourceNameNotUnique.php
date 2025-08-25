<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ResourceNameNotUnique extends ApiError {
	public function __construct() {
		parent::__construct(
			...$this->t("A resource cannot have the same name as a sibling resource"),
		);
	}
}
