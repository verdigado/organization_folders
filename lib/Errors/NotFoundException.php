<?php

namespace OCA\OrganizationFolders\Errors;

abstract class NotFoundException extends \RuntimeException {
	public function __construct(public readonly mixed $entity, public readonly array $criteria) {
		if(class_exists($entity)) {
			$entityParts = explode('\\', $entity);
			$entityName = array_pop($entityParts);
		} else {
			$entityName = $entity;
		}

		$message = sprintf(
			"Could not find %s with criteria %s",
			$entityName,
			json_encode($criteria),
		);
		parent::__construct($message);
	}
}
