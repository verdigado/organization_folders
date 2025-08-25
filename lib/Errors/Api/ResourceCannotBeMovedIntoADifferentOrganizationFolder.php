<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Db\Resource;

class ResourceCannotBeMovedIntoADifferentOrganizationFolder extends ApiError {
	public function __construct(public readonly Resource $resource) {
		parent::__construct(...$this->t("A resource cannot be moved into a different organization folder"));
	}
}
