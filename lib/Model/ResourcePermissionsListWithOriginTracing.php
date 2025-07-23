<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

class ResourcePermissionsListWithOriginTracing extends ResourcePermissionsList {

	public function addPermission(Principal $principal, int $permissions, ?array $permissionOrigin = null): ResourcePermission {
		$key = $principal->getKey();

		$existingPermission = $this->permissions[$key] ?? null;

		$newPermission = new ResourcePermission(
			principal: $principal,
			permissions: ($existingPermission?->getPermissions() ?? 0) | $permissions,
			permissionOrigins: array_merge(($existingPermission?->getPermissionOrigins() ?? []), [$permissionOrigin]),
		);

		$this->permissions[$key] = $newPermission;

		return $newPermission;
	}
}