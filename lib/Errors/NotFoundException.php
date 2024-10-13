<?php

namespace OCA\OrganizationFolders\Errors;

abstract class NotFoundException extends \RuntimeException {
	public function __construct($entity, array|string $criteria) {
		$message = sprintf(
			"Could not find %s with criteria %s",
			class_exists($entity) ? array_pop(explode('\\', $entity)) : $entity,
			is_string($criteria) ? $criteria : json_encode($criteria),
		);
		parent::__construct($message);
	}
}
