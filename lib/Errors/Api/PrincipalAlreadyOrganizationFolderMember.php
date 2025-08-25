<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Enum\PrincipalType;

class PrincipalAlreadyOrganizationFolderMember extends ApiError {
	public function __construct(
        public readonly Principal $principal,
        public readonly OrganizationFolder $organizationFolder,
    ) {
		$parameters = [
			$principal->getFriendlyName(),
			$principal->getId(),
			$organizationFolder->getName(),
			$organizationFolder->getId(),
		];

		if($principal->getType() === PrincipalType::USER) {
			parent::__construct(
				...$this->t("The user \"%s\" (id: %s) has already been added to organization folder \"%s\" (id: %s)", $parameters));
		} else if($principal->getType() === PrincipalType::GROUP) {
			parent::__construct(
				...$this->t("The group \"%s\" (id: %s) has already been added to organization folder \"%s\" (id: %s)", $parameters),
			);
		} else if($principal->getType() === PrincipalType::ORGANIZATION_MEMBER) {
			parent::__construct(
				...$this->t("The organization members of \"%s\" (id: %s) have already been added to organization folder \"%s\" (id: %s)", $parameters),
			);
		} else if($principal->getType() === PrincipalType::ORGANIZATION_ROLE) {
			parent::__construct(
				...$this->t("The organization role \"%s\" (id: %s) has already been added to organization folder \"%s\" (id: %s)", $parameters),
			);
		}
	}
}
