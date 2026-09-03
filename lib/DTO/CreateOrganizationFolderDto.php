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

class CreateOrganizationFolderDto extends BaseDto {
	
	private static ?Validatable $validator = null;

	public function __construct(
		public readonly string $name,
		public readonly ?int $quota = null,
		public readonly ?string $organizationProviderId = null,
		public readonly ?int $organizationId = null,
		public readonly ?string $serviceAccountUid = null,
	) {
		$this->validate();
	}

	public static function getValidator(): Validatable {
		return self::$validator ??= self::buildValidator();
	}

	private static function buildValidator(): Validatable {
		return v::create()
			->attribute('name', v::stringType())
			->attribute('quota', v::nullable(v::intType()))
			->oneOf(
				 v::allOf(
					v::attribute('organizationProviderId', v::nullType()),
					v::attribute('organizationId', v::nullType())
				),
				v::allOf(
					v::attribute('organizationProviderId', v::stringType()),
					v::attribute('organizationId', v::intType())
				),
			)
			->attribute('serviceAccountUid', v::nullable(v::stringType()));
	}
}
