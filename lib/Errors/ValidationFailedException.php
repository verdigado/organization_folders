<?php

namespace OCA\OrganizationFolders\Errors;

class ValidationFailedException extends \RuntimeException {
	private array $violations;

	public function __construct(string $message = 'Validation failed.', array $violations) {
		parent::__construct($message, 400, );
		$this->violations = $violations;
	}

	public function getViolations(): array {
		return $this->violations;
	}
}
