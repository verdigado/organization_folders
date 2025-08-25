<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\OrganizationFolder;

class PrincipalAlreadyOrganizationFolderMember extends ApiError {
	public function __construct(
        public readonly Principal $principal,
        public readonly OrganizationFolder $organizationFolder,
    ) {
		parent::__construct(
			...$this->t("Principal \"%s\" (id: %s) is already member of organization folder \"%s\" (id: %s)", [
				$principal->getFriendlyName(),
				$principal->getKey(),
				$organizationFolder->getName(),
				$organizationFolder->getId(),
			]),
        );
	}
}
