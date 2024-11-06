<?php

namespace OCA\OrganizationFolders\Security;

use OCP\IUser;

abstract class Voter implements VoterInterface {
	public function vote(?IUser $user, mixed $subject, array $attributes): int {
		// abstain vote by default in case none of the attributes are supported
		$vote = self::ACCESS_ABSTAIN;

		foreach ($attributes as $attribute) {
			try {
				if (!$this->supports($attribute, $subject)) {
					continue;
				}
			} catch (\TypeError $e) {
				if (str_contains($e->getMessage(), 'supports(): Argument #1')) {
					continue;
				}

				throw $e;
			}

			// as soon as at least one attribute is supported, default is to deny access
			$vote = self::ACCESS_DENIED;

			if ($this->voteOnAttribute($attribute, $subject, $user)) {
				// grant access as soon as at least one attribute returns a positive response
				return self::ACCESS_GRANTED;
			}
		}

		return $vote;
	}

	/**
	 * Return false if your voter doesn't support the given attribute. Symfony will cache
	 * that decision and won't call your voter again for that attribute.
	 */
	public function supportsAttribute(string $attribute): bool {
		return true;
	}

	/**
	 * Return false if your voter doesn't support the given subject type. Symfony will cache
	 * that decision and won't call your voter again for that subject type.
	 *
	 * @param string $subjectType The type of the subject inferred by `get_class()` or `get_debug_type()`
	 */
	public function supportsType(string $subjectType): bool {
		return true;
	}

	/**
	 * Determines if the attribute and subject are supported by this voter.
	 *
	 * @param $subject The subject to secure, e.g. an object the user wants to access or any other PHP type
	 */
	abstract protected function supports(string $attribute, mixed $subject): bool;

	/**
	 * Perform a single access check operation on a given attribute, subject and token.
	 * It is safe to assume that $attribute and $subject already passed the "supports()" method check.
	 */
	abstract protected function voteOnAttribute(string $attribute, mixed $subject, ?IUser $user): bool;
}
