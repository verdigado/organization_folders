<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

/**
 * Error typically thrown during an API request or occ command invocation.
 * Specifies a http eror code to be returned to the client when thrown during an API request.
 */
abstract class ApiError extends \RuntimeException {
	public function __construct(string $message, int $httpCode = Http::STATUS_BAD_REQUEST) {
		parent::__construct(message: $message, code: $httpCode);
	}

	public function getHttpCode(): int{
		return $this->getCode();
	}
}
