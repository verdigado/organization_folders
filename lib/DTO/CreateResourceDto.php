<?php

namespace OCA\OrganizationFolders\DTO;

use Respect\Validation\Validator as v;
use Respect\Validation\ChainedValidator;

readonly class CreateResourceDto {
	public function __construct(
		public int $organizationFolderId,
		public string $type,
		public string $name,
		public ?int $parentResourceId = null,
		public bool $active = true,
		public bool $inheritManagers = true,

		public int $membersAclPermission,
		public int $managersAclPermission,
		public int $inheritedAclPermission,
	) {}

	public static function GetValidator(): ChainedValidator {
		return v::create()
			->key('organizationFolderId', v::intType())
			->key('type', v::stringVal()->oneOf(
				v::equals('folder'),
			)->setTemplate('must be one of: folder'))
			->key('name', v::stringVal()->not(v::regex('/[`$%^*={};"\\\\|<>\/?~]/')))
			->key('parentResourceId', v::intType())
			->key('active', v::boolType())
			->key('inheritManagers', v::boolType())

			->when(v::key('type', v::stringVal()->equals('folder')), v::allOf(
				v::key('membersAclPermission', v::intVal()->between(0, 31)),
				v::key('managersAclPermission', v::intVal()->between(0, 31)),
				v::key('inheritedAclPermission', v::intVal()->between(0, 31)),
			));
	}
}
