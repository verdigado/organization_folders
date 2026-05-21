<?php

namespace OCA\OrganizationFolders\DTO;

use Respect\Validation\Validator as v;
use Respect\Validation\ChainedValidator;

readonly class CreateResourceDto {
	public function __construct(
		public int $organizationFolderId,
		public string $type,
		public string $name,
		public array $memberPermissions,
		public array $managerPermissions,
		public array $inheritedMemberPermissions,
		public ?int $parentResourceId = null,
		public bool $active = true,
		public bool $inheritManagers = true,
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
			->key('memberPermissions', v::arrayType())
			->key('managerPermissions', v::arrayType())
			->key('inheritedMemberPermissions', v::arrayType());
	}
}
