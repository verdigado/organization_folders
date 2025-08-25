<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

class AccessDenied extends ApiError {
	public function __construct(?string $message = null, ?string $l10nMessage = null) {
		if(!isset($message) || !isset($l10nMessage)) {
			[
				'message' => $message,
				'l10nMessage' => $l10nMessage,
			] = $this->t('Access Denied');
		}

		parent::__construct($message, $l10nMessage, Http::STATUS_FORBIDDEN);
	}
}
