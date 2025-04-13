<?php

namespace OCA\OrganizationFolders\Errors;

use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\OrganizationFolder;

class PrincipalAlreadyOrganizationFolderMember extends \RuntimeException {
	public function __construct(
        public readonly Principal $principal,
        public readonly OrganizationFolder $organizationFolder,
    ) {
		parent::__construct(
            message: "Principal " . $principal->getFriendlyName()
                . " (id: " . $principal->getType()->name . ":" . $principal->getId() . ")"
                . " is already member of organization folder " . $organizationFolder->getName()
                . " (id: " . $organizationFolder->getId() . ")",
        );
	}
}
