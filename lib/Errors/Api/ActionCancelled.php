<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

class ActionCancelled extends ApiError {
	public function __construct(
		?string $message = null,
		?string $l10nMessage = null,
		?string $id = null,
		int $httpCode = Http::STATUS_CONFLICT,
	) {
		$default = 'The action was cancelled.';
		parent::__construct(
			message: $message ?? $default,
			l10nMessage: $l10nMessage ?? $message ?? $default,
			httpCode: $httpCode,
			id: $id,
		);
	}
}

