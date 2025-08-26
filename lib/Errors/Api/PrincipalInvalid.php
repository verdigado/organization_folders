<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Enum\PrincipalType;

/**
 * Used for functions which only take valid principals when given an invalid principal
 */
class PrincipalInvalid extends ApiError {
	public function __construct(
        public readonly Principal $principal,
    ) {
		$parameters = [
			$principal->getId(),
		];

		if($principal->getType() === PrincipalType::USER) {
			parent::__construct(
				...$this->t("A user with ID %s does not exist", $parameters)
			);
		} else if($principal->getType() === PrincipalType::GROUP) {
			parent::__construct(
				...$this->t("A group with ID %s does not exist", $parameters),
			);
		} else if($principal->getType() === PrincipalType::ORGANIZATION_MEMBER) {
			parent::__construct(
				...$this->t("An organization with ID %s does not exist", $parameters),
			);
		} else if($principal->getType() === PrincipalType::ORGANIZATION_ROLE) {
			parent::__construct(
				...$this->t("An organization role with ID: %s does not exist", $parameters),
			);
		}
	}
}
