<?php

namespace OCA\OrganizationFolders\Errors;

use OCA\OrganizationFolders\Model\Principal;

class PrincipalAlreadyResourceMember extends \RuntimeException {
	public function __construct(Principal $principal, int $resourceId) {
		parent::__construct("Principal " . $principal->getType()->name . ":" . $principal->getId() . " is already member of resource " . $resourceId);
	}
}
