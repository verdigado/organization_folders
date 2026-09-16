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

class CreateResourceDto extends BaseDto {

	private static ?Validatable $validator = null;

	/**
	 * @param ?string $calendarUri special mode only for type = "calendar" to use a specific calendar URI instead of generating one
	 * 
	 * @param bool $alreadyExists special mode, that for type = "folder" uses an existing folder with the resource name
	 *                            and for type = "calendar" uses an existing calendar with the given id $existingCalendarId
	 */
	public function __construct(
		public readonly int $organizationFolderId,
		public readonly string $type,
		public readonly string $name,
		public readonly array $memberPermissions,
		public readonly array $managerPermissions,
		public readonly array $inheritedMemberPermissions,
		public readonly ?int $parentResourceId = null,
		public readonly bool $active = true,
		public readonly bool $inheritManagers = true,
		public readonly ?string $calendarUri = null,
		public readonly bool $alreadyExists = false,
		public readonly ?int $existingCalendarId = null,
	) {
		$this->validate();
	}

	public static function getValidator(): Validatable {
		return self::$validator ??= self::buildValidator();
	}

	private static function buildValidator(): Validatable {
		return v::create()
			->attribute('organizationFolderId', v::intType())
			->attribute('type', v::stringVal()->oneOf(
				v::equals('folder'),
				v::equals('calendar'),
			)->setTemplate('must be one of: folder, calendar'))
			->attribute('name', v::stringVal()->not(v::regex('/[`$%^*={};"\\\\|<>\/?~]/')))
			->attribute('memberPermissions', v::arrayVal()->each(v::boolType()))
			->attribute('managerPermissions', v::arrayVal()->each(v::boolType()))
			->attribute('inheritedMemberPermissions', v::arrayVal()->each(v::boolType()))
			->attribute('parentResourceId', v::nullable(v::intType()))
			->attribute('active', v::boolType())
			->attribute('inheritManagers', v::boolType())
			->oneOf(
				v::allOf(
					v::attribute('type', v::equals('calendar')),
					v::attribute('calendarUri', v::nullable(v::stringType())),
				),
				v::allOf(
					v::attribute('type', v::not(v::equals('calendar'))),
					v::attribute('calendarUri', v::nullType()),
				)
			)
			->oneOf(
				v::attribute('alreadyExists', v::equals(false)),
				v::allOf(
					v::attribute('type', v::equals('calendar')),
					v::attribute('alreadyExists', v::equals(true)),
					v::attribute('existingCalendarId', v::intType()),
				),
				v::allOf(
					v::attribute('type', v::not(v::equals('calendar'))),
					v::attribute('alreadyExists', v::equals(true)),
					v::attribute('existingCalendarId', v::nullType()),
				)
			);
	}
}
