<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

class ActionCancelled extends ApiError {
	public function __construct(
		string $message,
		int $httpCode = Http::STATUS_CONFLICT,
	) {
		parent::__construct(
			message: $message,
			l10nMessage: $message,
			httpCode: $httpCode,
		);
	}
}

