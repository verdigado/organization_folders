<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ValidationFailedException extends ApiError {
	private array $violations;

	public function __construct(string $message = 'Validation failed.', array $violations) {
		parent::__construct($message, $message);
		$this->violations = $violations;
	}

	public function getViolations(): array {
		return $this->violations;
	}
}
