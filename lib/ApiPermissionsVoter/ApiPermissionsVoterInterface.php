<?php

namespace OCA\OrganizationFolders\ApiPermissionsVoter;

use Closure;

use OCA\OrganizationFolders\Model\Criterion\Criterion;
use OCA\OrganizationFolders\Model\VoterDecision;
use OCA\OrganizationFolders\Model\Principal;

interface ApiPermissionsVoterInterface {
	/**
	 * @param Principal[] $principals
	 * @param VoterSubject[] $subjects
	 * @param list<string>|Closure(Principal, VoterSubject): list<string> $actions
	 * @param array &$scratchpad leave information for voters of higher priority and potentially for later queries
	 * @param Closure(Principal, VoterSubject, string): ?Criterion $currentCriterion returns criterion decided on by the voters of lower priority for a specific principal-subject-action combination, which
	 * will be overriden if this voter returns a decisions for it.
	 * Voter can wrap it by creating a AnyCriterion/AllCriterion with it as an operand therefore extending the previous criteria instead of replacing them.
	 * Only call with values from $principals, $subjects and $actions.
	 *
	 * @return array<string, array<string, array<string, VoterDecision>>> Result of vote for principal by key (depth 1), subject by key (depth 2) and action (depth 3) (excluding abstaining votes)
	 */
	public function vote(array $principals, array $subjects, array|Closure $actions, array &$scratchpad, Closure $currentCriterion): array;
}
