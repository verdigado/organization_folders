<?php

declare(strict_types=1);

/**
 * @author Jonathan Treffler <jonathan.treffler@verdigado.com>
 *
 * @license GNU AGPL version 3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OrganizationFolders\DTO;

use Respect\Validation\Validator as v;
use Respect\Validation\Validatable;

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
class CreateResourceWithMembersDto extends CreateResourceDto {

	private static ?Validatable $validator = null;


	/**
	 * @param ?string $calendarUri special mode only for type = "calendar" to use a specific calendar URI instead of generating one
	 * 
	 * @param bool $alreadyExists special mode, that for type = "folder" uses an existing folder with the resource name
	 *                            and for type = "calendar" uses an existing calendar with the given id $existingCalendarId
	 * 
	 * @param array{
	 *   permissionLevel: ResourceMemberPermissionLevel|string,
	 *   principalType: PrincipalType|string,
	 *   principalId: string,
	 * }[] $members
	 */
	public function __construct(
		int $organizationFolderId,
		string $type,
		string $name,
		array $memberPermissions,
		array $managerPermissions,
		array $inheritedMemberPermissions,
		?int $parentResourceId = null,
		bool $active = true,
		bool $inheritManagers = true,
		?string $calendarUri = null,
		bool $alreadyExists = false,
		?int $existingCalendarId = null,

		public readonly array $members = [],
	) {
		parent::__construct(
			organizationFolderId: $organizationFolderId,
			type: $type,
			name: $name,
			memberPermissions: $memberPermissions,
			managerPermissions: $managerPermissions,
			inheritedMemberPermissions: $inheritedMemberPermissions,
			parentResourceId: $parentResourceId,
			active: $active,
			inheritManagers: $inheritManagers,
			calendarUri: $calendarUri,
			alreadyExists: $alreadyExists,
			existingCalendarId: $existingCalendarId,
		);

		$this->validate();
	}

	public static function getValidator(): Validatable {
		return self::$validator ??= self::buildValidator();
	}

	private static function buildValidator(): Validatable {
		return v::allOf(
			parent::getValidator(),
			v::create()->attribute("members", v::arrayVal()->each(
				v::keySet(
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
			))
		);
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