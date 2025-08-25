<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Enum\PrincipalType;

class PrincipalAlreadyResourceMember extends ApiError {
	public function __construct(
		public readonly Principal $principal,
		public readonly Resource $resource,
	) {
		$parameters = [
			$principal->getFriendlyName(),
			$principal->getId(),
			$resource->getName(),
			$resource->getId(),
		];

		if($principal->getType() === PrincipalType::USER) {
			parent::__construct(
				...$this->t("The user \"%s\" (id: %s) has already been added to resource \"%s\" (id: %s)", $parameters));
		} else if($principal->getType() === PrincipalType::GROUP) {
			parent::__construct(
				...$this->t("The group \"%s\" (id: %s) has already been added to resource \"%s\" (id: %s)", $parameters),
			);
		} else if($principal->getType() === PrincipalType::ORGANIZATION_MEMBER) {
			parent::__construct(
				...$this->t("The organization members of \"%s\" (id: %s) have already been added to resource \"%s\" (id: %s)", $parameters),
			);
		} else if($principal->getType() === PrincipalType::ORGANIZATION_ROLE) {
			parent::__construct(
				...$this->t("The organization role \"%s\" (id: %s) has already been added to resource \"%s\" (id: %s)", $parameters),
			);
		}
	}
}
