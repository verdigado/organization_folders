<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

class AccessDenied extends ApiError {
	public function __construct(string $message = 'Access Denied.') {
		parent::__construct($message, Http::STATUS_FORBIDDEN);
	}
}
