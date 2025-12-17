<?php

namespace OCA\OrganizationFolders\DTO;

use Respect\Validation\Validator as v;
use Respect\Validation\Validatable;

use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;

readonly class CreateResourceMemberDto {

	public ResourceMemberPermissionLevel $permissionLevel;
	public PrincipalType $principalType;

	public function __construct(
		public int $resourceId,
		int|string|ResourceMemberPermissionLevel $permissionLevel,
		int|string|PrincipalType $principalType,
		public string $principalId,
	) {
		if($permissionLevel instanceof ResourceMemberPermissionLevel) {
			$this->permissionLevel = $permissionLevel;
		} else {
			$this->permissionLevel = ResourceMemberPermissionLevel::fromNameOrValue($permissionLevel);
		}

		if($principalType instanceof PrincipalType) {
			$this->principalType = $principalType;
		} else {
			$this->principalType = PrincipalType::fromNameOrValue($principalType);
		}
	}

	public static function GetValidator(): Validatable {
		return v::create()
			->key('resourceId', v::intType())
			->key('permissionLevel', v::oneOf(
				v::instance(ResourceMemberPermissionLevel::class),
				v::in(ResourceMemberPermissionLevel::getAllValidValues(), true)
			))
			->key('principalType', v::oneOf(
				v::instance(PrincipalType::class),
				v::in(PrincipalType::getAllValidValues(), true)
			))
			->key('principalId', v::stringType());
	}
}