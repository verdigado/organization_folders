<?php

namespace OCA\OrganizationFolders\Public\Errors;

use OCA\OrganizationFolders\Errors\Api\NotFoundException;

class OrganizationNotFound extends NotFoundException {
	public function __construct(
		public readonly string $providerId,
		public readonly int $id
	) {
		parent::__construct(
			entity: \OCA\OrganizationFolders\Model\Organization::class,
			criteria: ["providerId" => $providerId, "id" => $id],
		);
	}
}