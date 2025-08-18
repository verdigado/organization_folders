<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

class WouldCauseTooManyPermissionsChanges extends ApiError {
	public function __construct(
		private readonly int $numberOfUsersWithPermissionsAdded,
		private readonly int $numberOfUsersWithPermissionsDeleted,
	) {
		parent::__construct(
            message: "Request cancelled, because it would cause number of permissions additions or deletions above requested limit",
			httpCode: Http::STATUS_PRECONDITION_FAILED,
			id: "WouldCauseTooManyPermissionsChanges",
        );
	}

	public function getDetails(): ?array {
		return [
			"numberOfUsersWithPermissionsAdded" => $this->numberOfUsersWithPermissionsAdded,
			"numberOfUsersWithPermissionsDeleted" => $this->numberOfUsersWithPermissionsDeleted,
		];
	}
}
