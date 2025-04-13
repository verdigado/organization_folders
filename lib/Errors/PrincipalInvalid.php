<?php

namespace OCA\OrganizationFolders\Errors;

use OCA\OrganizationFolders\Model\Principal;

class PrincipalInvalid extends \RuntimeException {
	public function __construct(
        public readonly Principal $principal,
    ) {
		parent::__construct(
            message: "Principal of type " . $principal->getType()->name . " and id " . $principal->getId() . " is invalid/does not exist",
        );
	}
}
