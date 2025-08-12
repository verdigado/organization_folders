<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Db\Resource;

class PrincipalAlreadyResourceMember extends ApiError {
	public function __construct(
		public readonly Principal $principal,
		public readonly Resource $resource,
	) {
		parent::__construct(
            message: "Principal " . $principal->getFriendlyName()
                . " (id: " . $principal->getKey() . ")"
                . " is already member of resource " . $resource->getName()
                . " (id: " . $resource->getId() . ")",
        );
	}
}
