<?php

namespace OCA\OrganizationFolders\DTO;

use Respect\Validation\Validator as v;
use Respect\Validation\ChainedValidator;

use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;

/**
 * This DTO encapsulates the creation of a Resource and the subsequent creation of members of that resource.
 * 
 * Usage of DTO:
 *   - create resource with ResourceService
 *   - call splitOutCreateMemberDTOs with the id of the newly created resource
 *   - iterate over the returned CreateResourceMemberDtos and create them with using ResourceMemberService
 */
readonly class CreateResourceWithMembersDto extends CreateResourceDto {

	public function __construct(
		int $organizationFolderId,
		string $type,
		string $name,
		?int $parentResourceId = null,
		bool $active = true,
		bool $inheritManagers = true,

		int $membersAclPermission,
		int $managersAclPermission,
		int $inheritedAclPermission,

		public array $members = [],
	) {
		parent::__construct(
			organizationFolderId: $organizationFolderId,
			type: $type,
			name: $name,
			parentResourceId: $parentResourceId,
			active: $active,
			inheritManagers: $inheritManagers,

			membersAclPermission: $membersAclPermission,
			managersAclPermission: $managersAclPermission,
			inheritedAclPermission: $inheritedAclPermission,
		);
	}
	public static function GetValidator(): ChainedValidator {
		return parent::GetValidator()->key("members", v::arrayVal()->each(
			v::allOf(
				v::key("permissionLevel", v::oneOf(
					v::instance(ResourceMemberPermissionLevel::class),
					v::in(ResourceMemberPermissionLevel::getAllValidValues(), true)
				)),
				v::key("principalType", v::oneOf(
					v::instance(PrincipalType::class),
					v::in(PrincipalType::getAllValidValues(), true)
				)),
				v::key("principalId", v::stringType()),
			)
		));
	}

	/**
	 * @param int $resourceId
	 * @return CreateResourceMemberDto[]
	 */
	public function splitOutCreateMemberDTOs(int $resourceId): array {
		$result = [];

		foreach($this->members as $member) {
			$result[] = new CreateResourceMemberDto(
				resourceId: $resourceId,
				permissionLevel: $member["permissionLevel"],
				principalType: $member["principalType"],
				principalId: $member["principalId"],
			);
		}

		return $result;
	}
}