<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory;

use OCP\IL10N;

use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\ResourceAnySubResourceManagerCriterion;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Model\Criterion\CriterionFactory;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Service\ResourceService;

class ResourceAnySubResourceManagerCriterionFactory extends CriterionFactory {

	/** @var array<string, array<int, ResourceAnySubResourceManagerCriterion>> */
	private array $cache = [];

	public function __construct(
		private readonly IL10N $l10n,
		private readonly ResourceService $resourceService,
		private array &$scratchpad,
	) {}

	public function build(Principal $principal, Resource $resource): ResourceAnySubResourceManagerCriterion {
		$principalKey = $principal->getKey();

		if(isset($this->cache[$principalKey])){
			if(isset($this->cache[$principalKey][$resource->getId()])) {
				return $this->cache[$principalKey][$resource->getId()];
			}
		} else {
			$this->cache[$principalKey] = [];
		}
		
		return $this->cache[$principalKey][$resource->getId()] = new ResourceAnySubResourceManagerCriterion(
			$this->l10n,
			$this->resourceService,
			$principal,
			$resource,
			$this->scratchpad,
		);
	}

	public function buildFromId(Principal $principal, int $resourceId) {
		$principalKey = $principal->getKey();

		if(isset($this->cache[$principalKey])){
			if(isset($this->cache[$principalKey][$resourceId])) {
				return $this->cache[$principalKey][$resourceId];
			}
		}

		$resource = $this->resourceService->find($resourceId);

		return $this->build($principal, $resource);
	}
}