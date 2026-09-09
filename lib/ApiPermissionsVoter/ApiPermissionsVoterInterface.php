<?php

namespace OCA\OrganizationFolders\ApiPermissionsVoter;

use Closure;

use OCA\OrganizationFolders\Model\VoterDecision;
use OCA\OrganizationFolders\Model\Principal;

interface ApiPermissionsVoterInterface {
	/**
	 * @param Principal[] $principals
	 * @param VoterSubject[] $subjects
	 * @param list<string>|Closure(Principal, VoterSubject): list<string> $actions
	 * @param array &$scratchpad leave information for voters of higher priority and potentially for later queries
	 *
	 * @return array<string, array<string, array<string, VoterDecision>>> Result of vote for principal by key (depth 1), subject by key (depth 2) and action (depth 3) (excluding abstaining votes)
	 */
	public function vote(array $principals, array $subjects, array|Closure $actions, array &$scratchpad): array;
}
