<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Groups\GroupBackend;

use OCA\GroupFolders\ACL\UserMapping\UserMapping;

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

	public function addPermission(Principal $principal, int $permissions, ?array $permissionOrigin = null): ResourcePermission {
		$key = $principal->getKey();

		$existingPermission = $this->permissions[$key] ?? null;

		$newPermission = new ResourcePermission(
			principal: $principal,
			permissions: ($existingPermission?->getPermissions() ?? 0) | $permissions,
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

	public function toGroupfolderAclList(): AclList {
		if(!($this->resource instanceof FolderResource)) {
			throw new \Exception("Only folder resources can be transformed to an AclList");
		}

		$acls = new AclList($this->resource->getFileId());

		// add default deny
		$acls->addRule(
			userMapping: new UserMapping(type: "group", id: GroupBackend::EVERYONE_GROUP, displayName: null),
			mask: 31,
			permissions: 0,
		);

		foreach($this->getPermissions() as $permission) {
			$acls->addRule(
				userMapping: $permission->getPrincipal()->toGroupfolderAclMapping(),
				mask: 31,
				permissions: $permission->getPermissions(),
			);
		}

		return $acls;
	}
}