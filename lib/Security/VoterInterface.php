<?php

namespace OCA\OrganizationFolders\Security;

use OCP\IUser;

interface VoterInterface {
	public const ACCESS_GRANTED = 1;
	public const ACCESS_ABSTAIN = 0;
	public const ACCESS_DENIED = -1;

	/**
	 * Returns the vote for the given parameters.
	 *
	 * This method must return one of the following constants:
	 * ACCESS_GRANTED, ACCESS_DENIED, or ACCESS_ABSTAIN.
	 *
	 * @param mixed $subject    The subject to secure
	 * @param array $attributes An array of attributes associated with the method being invoked
	 *
	 * @return int either ACCESS_GRANTED, ACCESS_ABSTAIN, or ACCESS_DENIED
	 */
	public function vote(?IUser $user, mixed $subject, array $attributes): int;
}
