<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

class ResourcePermission {
	public function __construct(
		private readonly Principal $principal,
		private readonly int $permissions
	){}

	public function getPrincipal(): Principal {
		return $this->principal;
	}

	public function getPermissions(): int {
		return $this->permissions;
	}
}