<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

/**
 * Datastructure used by ResourcePermissionsList to associate a principal with certain permissions and optionally keep track of the origins of this permission.
 */
class ResourcePermission implements \JsonSerializable {
	public function __construct(
		private readonly Principal $principal,
		private readonly int $permissions,
		private readonly ?array $permissionOrigins = null,
	){}

	public function getPrincipal(): Principal {
		return $this->principal;
	}

	public function getPermissions(): int {
		return $this->permissions;
	}

	public function getPermissionOrigins(): array {
		return $this->permissionOrigins;
	}

	public function jsonSerialize(): array {
		return [
			'principal' => $this->principal,
			'permissions' => $this->permissions,
			'permissionOrigins' => $this->permissionOrigins,
		];
	}
}