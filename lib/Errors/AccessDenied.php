<?php

namespace OCA\OrganizationFolders\Errors;

class AccessDenied extends \RuntimeException {
	public function __construct(string $message = 'Access Denied.', \Throwable $previous = null) {
		parent::__construct($message, 403, $previous);
	}
}
