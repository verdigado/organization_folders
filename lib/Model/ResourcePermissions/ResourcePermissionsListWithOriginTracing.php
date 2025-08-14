<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Enum\PermissionOriginType;

class ResourcePermissionsListWithOriginTracing extends ResourcePermissionsList {

	public function addPermission(Principal $principal, int $permissionsBitmap, ?PermissionOriginType $permissionOriginType = null, OrganizationFolder|Resource|null $permissionInheritedFrom = null): ResourcePermission {
		$key = $principal->getKey();

		$existingPermission = $this->permissions[$key] ?? null;

		$newPermissionsOrigin = [
			"type" => $permissionOriginType,
			"permissionsBitmap" => $permissionsBitmap,
		];

		if(isset($permissionInheritedFrom)) {
			$newPermissionsOrigin["inheritedFrom"] = $permissionInheritedFrom;
		}

		$newPermission = new ResourcePermission(
			principal: $principal,
			permissionsBitmap: ($existingPermission?->getPermissionsBitmap() ?? 0) | $permissionsBitmap,
			permissionOrigins: array_merge(($existingPermission?->getPermissionOrigins() ?? []), [$newPermissionsOrigin]),
		);

		$this->permissions[$key] = $newPermission;

		return $newPermission;
	}
}