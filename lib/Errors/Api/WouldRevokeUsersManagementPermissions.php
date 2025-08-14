<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

class WouldRevokeUsersManagementPermissions extends ApiError {
	public function __construct() {
		parent::__construct(
            message: "Request cancelled, because it would cause user to loose the management permissions they used to send the request",
			httpCode: Http::STATUS_PRECONDITION_FAILED,
        );
	}
}