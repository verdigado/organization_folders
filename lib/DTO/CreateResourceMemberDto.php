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

class CreateResourceMemberDto extends BaseDto {

	private static ?Validatable $validator = null;

	public readonly ResourceMemberPermissionLevel $permissionLevel;
	public readonly PrincipalType $principalType;

	public function __construct(
		public readonly int $resourceId,
		int|string|ResourceMemberPermissionLevel $permissionLevel,
		int|string|PrincipalType $principalType,
		public readonly string $principalId,
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

		$this->validate();
	}

	public static function getValidator(): Validatable {
		return self::$validator ??= self::buildValidator();
	}

	private static function buildValidator(): Validatable {
		return v::create()
			->attribute('resourceId', v::intType())
			->attribute('permissionLevel', v::instance(ResourceMemberPermissionLevel::class))
			->attribute('principalType', v::instance(PrincipalType::class))
			->attribute('principalId', v::stringType());
	}
}