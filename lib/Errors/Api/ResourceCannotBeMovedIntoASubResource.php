<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Db\Resource;

class ResourceCannotBeMovedIntoASubResource extends ApiError {
	public function __construct(public readonly Resource $resource) {
		parent::__construct(...$this->t("A resource cannot be moved into a subresource of itself"));
	}
}
