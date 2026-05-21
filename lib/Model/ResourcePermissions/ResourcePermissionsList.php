<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Enum\PermissionOriginType;

/**
 * Records which Prinicipals have which permissions in a Resource (including through inheritance).
 * Can be converted to a resource type specific object (AclList for folders), that can be used to apply it to the underlying permissions enforcement system (like groupfolder ACLs)
 */
class ResourcePermissionsList {
	/** @var array[string]ResourcePermission */
	protected array $permissions = [];

	public function __construct(protected Resource $resource) {}

	public function getResource(): Resource {
		return $this->resource;
	}

	public function addPermission(Principal $principal, int $permissionsBitmap, ?PermissionOriginType $permissionOriginType = null, OrganizationFolder|Resource|null $permissionInheritedFrom = null): ResourcePermission {
		$key = $principal->getKey();

		$existingPermission = $this->permissions[$key] ?? null;

		$newPermission = new ResourcePermission(
			principal: $principal,
			permissionsBitmap: ($existingPermission?->getPermissionsBitmap() ?? 0) | $permissionsBitmap,
		);

		$this->permissions[$key] = $newPermission;

		return $newPermission;
	}

	/**
	 * @psalm-return ResourcePermission[]
	 */
	public function getPermissions(): array {
		return array_values($this->permissions);
	}
}