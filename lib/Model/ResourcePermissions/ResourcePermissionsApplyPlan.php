<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCA\OrganizationFolders\Db\Resource;

abstract class ResourcePermissionsApplyPlan {
	abstract function getResource(): Resource;

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