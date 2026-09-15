<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ApiResponse;

use OCA\OrganizationFolders\Errors\Api\AccessDenied;
use OCA\OrganizationFolders\Errors\Api\IncludeInvalid;

abstract class ApiResponseFactory {

	public const MODEL_INCLUDE = 'model';

	/** @var array<string, \Closure> */
    private readonly array $includeHandlers;

	/**
	 * @param array<string, \Closure> $handlers
	 * @return void
	 */
	protected function setIncludeHandlers(array $handlers) {
		$this->includeHandlers = $handlers;
	}

	/**
	 * Return wether the user is permitted to see this entity at all
	 * (individual include handlers then decide what information about the entity the users is allowed to view)
	 * 
	 * @return bool
	 */
	abstract protected function filter(mixed $entity, array $apiPermissionsScratchpad): bool;

	// TODO: add filterMultiple() for more efficient permissions checks

	/**
	 * @param mixed $entity
	 * @param string[] $includes
	 * @param bool $throwIfNoAccess if false returns empty object instead of throwing
	 * 
	 * @throws AccessDenied
	 * 
	 * @return array<string, mixed>|\stdClass
	 */
	public function buildResponseForOne(mixed $entity, array $includes, array $apiPermissionsScratchpad, bool $throwIfNoAccess = true): array|\stdClass {
		if(!$this->filter($entity, $apiPermissionsScratchpad)) {
			if($throwIfNoAccess) {
				throw new AccessDenied();
			} else {
				return new \stdClass();
			}
		}

		$response = [];

		foreach($includes as $include) {
			$handler = $this->includeHandlers[$include] ?? null;

			if($handler === null) {
				throw new IncludeInvalid($include);
			} else {
				$handler($entity, $apiPermissionsScratchpad, $response);
			}
		}

		if(empty($response)) {
			return new \stdClass();
		} else {
			return $response;
		}
	}

	/**
	 * @param list $entities
	 * @param string[] $includes
	 * 
	 * @return array<string, mixed>
	 */
	public function buildResponseForMultiple(array $entities, array $includes, array $apiPermissionsScratchpad): array {
		$response = [];
		
		foreach($entities as $entity) {
			if($this->filter($entity, $apiPermissionsScratchpad)) {
				$entityResponse = [];
				
				foreach($includes as $include) {
					$handler = $this->includeHandlers[$include] ?? null;

					if($handler === null) {
						throw new IncludeInvalid($include);
					} else {
						$handler($entity, $apiPermissionsScratchpad, $entityResponse);
					}
				}

				if(!empty($entityResponse)) {
					$response[] = $entityResponse;
				}
			}
		}

		return $response;
	}
}