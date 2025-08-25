<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCP\AppFramework\Http;

abstract class NotFoundException extends ApiError {
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

		// TODO: find way to l10n this (logic probably needs to be move to the child classes)
		parent::__construct($message, $message, Http::STATUS_NOT_FOUND);
	}
}
