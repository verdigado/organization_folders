<?php

namespace OCA\OrganizationFolders\Validation;

use OCA\OrganizationFolders\Errors\ValidationFailedException;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator;

class ValidatorService {
	/**
	 * @throws ValidationFailedException
	 * @throws \LogicException
	 */
	public function validateAndCreate($input, string $class) {
		if (!method_exists($class, 'GetValidator')) {
			throw new \LogicException('class missing static method GetValidator');
		}

		$this->validate($class::GetValidator(), $input);

		return new $class(...$input);
	}

	/**
	 * @throws ValidationFailedException
	 */
	public function validate(Validator $validator, $input) {
		$violations = $this->getViolations($validator, $input);
		if (count($violations) !== 0) {
			throw new ValidationFailedException('Validation failed', $violations);
		}
	}

	public function getViolations(Validator $validator, $input): array {
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
