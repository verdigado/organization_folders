<?php

declare(strict_types=1);

/**
 * @author Jonathan Treffler <jonathan.treffler@verdigado.com>
 * @author Niko Heller <niko.heller@verdigado.com>
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

use OCA\OrganizationFolders\Errors\Api\ValidationFailedException;

use Respect\Validation\Validatable;
use Respect\Validation\Exceptions\NestedValidationException;

abstract class BaseDto {

	abstract public static function getValidator(): Validatable;
	
	/**
	 * @throws ValidationFailedException
	 */
	protected function validate() {
		$violations = $this->getViolations($this::getValidator(), $this);

		if (count($violations) !== 0) {
			throw new ValidationFailedException('Validation failed', $violations);
		}
	}

	private function getViolations(Validatable $validator, BaseDto $input): array {
		try {
			$validator->assert($input);
		} catch (NestedValidationException $e) {
			return $this->formatViolations($e);
		}

		return [];
	}

	private function formatViolations(NestedValidationException $e): array {
		$violations = [];
		foreach ($e->getMessages() as $key => $message) {
			if (!array_key_exists($key, $violations)) {
				$violations[$key] = [];
			}
			$violations[$key][] = [
				'field' => $key,
				'message' => $message,
			];
		}

		return $violations;
	}
}