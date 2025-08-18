<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

/**
 * Error typically thrown during an API request or occ command invocation.
 */
abstract class ApiError extends \RuntimeException {

	/**
	 * @param string $message
	 * @param int $httpCode http eror code to be returned to the client when thrown during an API request.
	 * @param mixed $id For errors that need to be recognized and handled in the frontend
	 */
	public function __construct(string $message, int $httpCode = Http::STATUS_BAD_REQUEST, private ?string $id = null) {
		parent::__construct(message: $message, code: $httpCode);
	}
	
	public function getId(): ?string {
		return $this->id;
	}

	public function getHttpCode(): int{
		return $this->getCode();
	}

	/** 
	 * @return array<string, mixed>|null
	 */
	public function getDetails(): ?array {
		return null;
	}
}
