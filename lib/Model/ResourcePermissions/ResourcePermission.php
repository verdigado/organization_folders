<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCA\OrganizationFolders\Model\Principal;

/**
 * Datastructure used by ResourcePermissionsList to associate a principal with certain permissions and optionally keep track of the origins of this permission.
 */
class ResourcePermission implements \JsonSerializable {
	public function __construct(
		private readonly Principal $principal,
		private readonly int $permissionsBitmap,
		private readonly ?array $permissionOrigins = null,
	){}

	public function getPrincipal(): Principal {
		return $this->principal;
	}

	public function getPermissionsBitmap(): int {
		return $this->permissionsBitmap;
	}

	public function getPermissionOrigins(): array {
		return $this->permissionOrigins;
	}

	public function jsonSerialize(): array {
		return [
			'principal' => $this->principal,
			'permissionsBitmap' => $this->permissionsBitmap,
			'permissionOrigins' => $this->permissionOrigins,
		];
	}
}