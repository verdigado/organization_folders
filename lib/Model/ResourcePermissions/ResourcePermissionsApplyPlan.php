<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCA\OrganizationFolders\Db\Resource;

abstract class ResourcePermissionsApplyPlan {
	abstract function getResource(): Resource;

	/**
	 * Contents of items are resource-type-specific
	 * @psalm-return list<non-empty-array<string, mixed>>
	 */
	abstract function getAdditions(): array;

	/**
	 * Contents of items are resource-type-specific
	 * @psalm-return list<non-empty-array<string, mixed>>
	 */
	abstract function getUpdates(): array;

	/**
	 * Contents of items are resource-type-specific
	 * @psalm-return list<non-empty-array<string, mixed>>
	 */
	abstract function getDeletions(): array;


	function getNumberOfChanges(): int {
		return $this->getNumberOfAdditions()
			+ $this->getNumberOfUpdates()
			+ $this->getNumberOfDeletions();
	}

	abstract function getNumberOfAdditions(): int;

	abstract function getNumberOfUpdates(): int;

	abstract function getNumberOfDeletions(): int;


	function getNumberOfEffectivePermissionsChanges(): int {
		return $this->getNumberOfEffectivePermissionsAdditions()
			+ $this->getNumberOfEffectivePermissionsUpdates()
			+ $this->getNumberOfEffectivePermissionsDeletions();
	}

	abstract function getNumberOfEffectivePermissionsAdditions(): int;

	abstract function getNumberOfEffectivePermissionsUpdates(): int;

	abstract function getNumberOfEffectivePermissionsDeletions(): int;
	

	abstract function getNumberOfUsersWithPermissionsPotentiallyAdded(): int;

	abstract function getNumberOfUsersWithPermissionsPotentiallyChanged(): int;

	abstract function getNumberOfUsersWithPermissionsPotentiallyDeleted(): int;
	
	
	abstract function apply(): void;
}