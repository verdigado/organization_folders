<?php

namespace OCA\OrganizationFolders\Public\Errors;

use OCA\OrganizationFolders\Errors\Api\NotFoundException;

class OrganizationRoleNotFound extends NotFoundException {
	public function __construct(
		public readonly string $providerId,
		public readonly string $id
	) {
		parent::__construct(
			entity: \OCA\OrganizationFolders\Model\OrganizationRole::class,
			criteria: ["providerId" => $providerId, "id" => $id],
		);
	}
}