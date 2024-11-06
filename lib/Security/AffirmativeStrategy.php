<?php

namespace OCA\OrganizationFolders\Security;

class AffirmativeStrategy implements \Stringable {
	private bool $allowIfAllAbstainDecisions;

	public function __construct(bool $allowIfAllAbstainDecisions = false) {
		$this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
	}

	public function decide(\Traversable $results): bool {
		$deny = 0;
		foreach ($results as $result) {
			if (VoterInterface::ACCESS_GRANTED === $result) {
				return true;
			}

			if (VoterInterface::ACCESS_DENIED === $result) {
				++$deny;
			}
		}

		if ($deny > 0) {
			return false;
		}

		return $this->allowIfAllAbstainDecisions;
	}

	public function __toString(): string {
		return 'affirmative';
	}
}
