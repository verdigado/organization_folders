<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Model\Principal;

/**
 * Used for functions which only take valid principals when given an invalid principal
 */
class PrincipalInvalid extends ApiError {
	public function __construct(
        public readonly Principal $principal,
    ) {
		parent::__construct(
            message: "Principal of type " . $principal->getType()->name . " and id " . $principal->getId() . " is invalid/does not exist",
        );
	}
}
