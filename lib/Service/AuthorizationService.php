<?php

namespace OCA\OrganizationFolders\Service;

use OCA\OrganizationFolders\ApiPermissionsVoter\VoterSubject;
use OCA\OrganizationFolders\Model\ApiAuthorization\ApiAuthorizationDecision;
use OCA\OrganizationFolders\Model\Criterion\AlwaysUnsatisfiedCriterion;
use OCA\OrganizationFolders\Model\Criterion\Criterion;
use OCA\OrganizationFolders\Model\Criterion\CriterionSatisfied;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Model\VoterDecision;
use OCA\OrganizationFolders\Registry\ApiPermissionsVoterRegistry;
use OCP\IUserSession;

class AuthorizationService {

	public function __construct(
		private readonly IUserSession $userSession,
		private readonly ApiPermissionsVoterRegistry $registry,
		private readonly PrincipalFactory $principalFactory,
	) {}

	/**
	 * @param array<string, mixed> $criterionTypeBlocklist associative array used as a set (values are ignored); criterions of the given types will force-evaluate to CriterionUnsatisfied
	 * @param bool $allReasons return all reasons for decision in ApiAuthorizationDecision
	 * @param ?Principal $principal if unset/null uses UserPrincipal of current session
	 * 
	 * @return ApiAuthorizationDecision
	 */
	public function decideOne(VoterSubject $subject, string $action, array &$scratchpad = [], array $criterionTypeBlocklist = [], bool $allReasons = false, ?Principal $principal = null): ApiAuthorizationDecision {
		if($principal === null) {
			$principal = $this->getSessionUserPrincipal();
		}

		$criterion = $this->getCriterion($principal, $subject, $action, $scratchpad);
		$criterionResult = $criterion->evaluate($allReasons, $criterionTypeBlocklist);

		return ApiAuthorizationDecision::fromCriterionResult($criterionResult, $allReasons);
	}

	/**
	 * @param string[] $actions
	 * @param array<string, mixed> $criterionTypeBlocklist associative array used as a set (values are ignored); criterions of the given types will force-evaluate to CriterionUnsatisfied
	 * @param bool $allReasons return all reasons for decision in ApiAuthorizationDecision
	 * @param ?Principal $principal if unset/null uses UserPrincipal of current session
	 * 
	 * @return array<string, ApiAuthorizationDecision>
	 */
	public function decideMultipleActions(VoterSubject $subject, array $actions, array &$scratchpad = [], array $criterionTypeBlocklist = [], bool $allReasons = false, ?Principal $principal = null): array {
		if($principal === null) {
			$principal = $this->getSessionUserPrincipal();
		}

		$criterionByAction = $this->getCriteriaSinglePrincipalSingleSubject($principal, $subject, $actions, $scratchpad);

		$result = [];

		foreach($actions as $action) {
			$criterion = $criterionByAction[$action] ?? null;

			$criterionResult = $criterion->evaluate($allReasons, $criterionTypeBlocklist);

			$result[$action] = ApiAuthorizationDecision::fromCriterionResult($criterionResult, $allReasons);
		}

		return $result;
	}

	private function getSessionUserPrincipal() {
		$user = $this->userSession->getUser();
		return $this->principalFactory->buildFromIUser($user);
	}

	public function isGrantedAny(VoterSubject $subject, array $actions, array &$scratchpad = []): bool {
		foreach($this->areGranted($subject, $actions, $scratchpad) as $granted) {
			if($granted) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param VoterSubject $subject
	 * @param string $action
	 * @param array<string, mixed> $criterionTypeBlocklist associative array used as a set (values are ignored); criterions of the given types will force-evaluate to CriterionUnsatisfied
	 * @return bool
	 */
	public function isGranted(VoterSubject $subject, string $action, array &$scratchpad = [], array $criterionTypeBlocklist = []): bool {
		$userPrincipal = $this->getSessionUserPrincipal();

		$criterion = $this->getCriterion($userPrincipal, $subject, $action, $scratchpad);
		$criterionResult = $criterion->evaluate(false, $criterionTypeBlocklist);

		return ($criterionResult instanceof CriterionSatisfied);
	}

	/**
	 * @param string[] $actions
	 * @param array<string, mixed> $criterionTypeBlocklist associative array used as a set (values are ignored); criterions of the given types will force-evaluate to CriterionUnsatisfied
	 * 
	 * @return array<string, bool>
	 */
	public function areGranted(VoterSubject $subject, array $actions, array &$scratchpad = [], array $criterionTypeBlocklist = []): array {
		$userPrincipal = $this->getSessionUserPrincipal();

		$criterionByAction = $this->getCriteriaSinglePrincipalSingleSubject($userPrincipal, $subject, $actions, $scratchpad);

		$result = [];

		foreach($actions as $action) {
			$criterion = $criterionByAction[$action] ?? null;

			$criterionResult = $criterion->evaluate(false, $criterionTypeBlocklist);

			$result[$action] = ($criterionResult instanceof CriterionSatisfied);
		}

		return $result;
	}

	/**
	 * Filters list of subjects to those where the user is granted any of the given actions
	 * 
	 * @param VoterSubject[] $subjects
	 * @param string[] $actions
	 * @param array<string, mixed> $criterionTypeBlocklist associative array used as a set (values are ignored); criterions of the given types will force-evaluate to CriterionUnsatisfied
	 * 
	 * @return VoterSubject[]
	 */
	public function filterSubjectsToGranted(array $subjects, array $actions, array &$scratchpad = [], array $criterionTypeBlocklist = []): array {
		$result = [];

		$userPrincipal = $this->getSessionUserPrincipal();

		$criteriaBySubjectAndAction = $this->getCriteria([$userPrincipal], $subjects, $actions, $scratchpad)[$userPrincipal->getKey()];

		foreach($subjects as $subject) {
			$criteriaByAction = &$criteriaBySubjectAndAction[$subject->getVoterSubjectKey()];

			foreach($actions as $action) {
				if($criteriaByAction[$action]->evaluate(criterionTypeBlocklist: $criterionTypeBlocklist) instanceof CriterionSatisfied) {
					$result[] = $subject;
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * @param Principal[] $principals
	 * @param VoterSubject[] $subjects
	 * @param list<string> $actions
	 * @param array &$scratchpad
	 * @return array<string, array<string, array<string, Criterion>>> Criterion by principal key (depth 1), by subject key (depth 2) and by action (depth 3)
	 */
	private function getCriteria(array $principals, array $subjects, array $actions, array &$scratchpad): array {
		$result = [];

		if(count($principals) === 0) {
			return $result;
		}

		$remainingActionsByPrincipalAndSubject = [];

		foreach($principals as $principal) {
			$principalKey = $principal->getKey();

			$result[$principalKey] = [];
			$remainingActionsByPrincipalAndSubject[$principalKey] = [];
			foreach($subjects as $subject) {
				$subjectKey = $subject->getVoterSubjectKey();

				$result[$principalKey][$subjectKey] = [];
				$remainingActionsByPrincipalAndSubject[$principalKey][$subjectKey] = $actions;
			}
		}

		if(count($subjects) === 0) {
			return $result;
		}

		/**
		 * @var array<string, list<VoterSubject>>
		 */
		$subjectsByType = [];

		foreach($subjects as $subject) {
			$subjectType = $subject::VOTER_SUBJECT_TYPE;

			if(!isset($subjectsByType[$subjectType])) {
				$subjectsByType[$subjectType] = [];
			}

			$subjectsByType[$subjectType][] = $subject;
		}

		$getRemainingActions = function (Principal $principal, VoterSubject $subject) use (&$remainingActionsByPrincipalAndSubject) {
			return $remainingActionsByPrincipalAndSubject[$principal->getKey()][$subject->getVoterSubjectKey()] ?? [];
		};

		$getCurrentCriterion = function (Principal $fPrincipal, VoterSubject $fSubject, string $fAction) use (&$result) {
			return $result[$fPrincipal->getKey()][$fSubject->getVoterSubjectKey()][$fAction] ?? null;
		};

		foreach($subjectsByType as $type => $subjectsOfType) {
			$votersForType = $this->registry->getVotersForSubjectType($type);

			if(empty($votersForType)) {
				foreach($principals as $principal) {
					$principalResult = &$result[$principal->getKey()];
					foreach($subjectsOfType as $subject) {
						$principalSubjectResult = &$principalResult[$subject->getVoterSubjectKey()];
						foreach($actions as $action) {
							$principalSubjectResult[$action] = new AlwaysUnsatisfiedCriterion("builtin:noVoterVoted");
						}
					}
				}

				continue;
			}

			$lastVoterIndex = array_key_last($votersForType);

			foreach ($votersForType as $voterIndex => $voter) {
				$isLastVoter = $voterIndex === $lastVoterIndex;

				$voterDecisionByPrincipalSubjectAndAction = $voter->vote(
					$principals,
					$subjectsOfType,
					$getRemainingActions,
					$scratchpad,
					$getCurrentCriterion,
				);

				foreach($principals as $principal) {
					$principalKey = $principal->getKey();
					
					$voterDecisionBySubjectAndAction = &$voterDecisionByPrincipalSubjectAndAction[$principalKey] ?? [];
					$principalResult = &$result[$principalKey];
					$remainingActionsBySubject = &$remainingActionsByPrincipalAndSubject[$principalKey];

					foreach($subjectsOfType as $subject) {
						$subjectKey = $subject->getVoterSubjectKey();

						$voterDecisionByAction = &$voterDecisionBySubjectAndAction[$subjectKey] ?? [];
						$principalSubjectResult = &$principalResult[$subjectKey];

						$finalActions = [];

						foreach($remainingActionsBySubject[$subjectKey] as $action) {
							$voterDecision = $voterDecisionByAction[$action] ?? null;

							if($voterDecision instanceof VoterDecision) {
								$principalSubjectResult[$action] = $voterDecision->criterion;

								if($voterDecision->final) {
									$finalActions[] = $action;
								}
							} else {
								if(!isset($principalSubjectResult[$action]) && $isLastVoter) {
									$principalSubjectResult[$action] = new AlwaysUnsatisfiedCriterion("builtin:noVoterVoted");
								}
							}
						}

						if(count($finalActions) !== 0) {
							$remainingActionsBySubject[$subjectKey] = array_diff($remainingActionsBySubject[$subjectKey], $finalActions);
						}
					}
				}
			}
		}

		return $result;
	}

	/**
	 * optimized version of getCriteria() for a single principal-subject combination
	 * 
	 * @param Principal $principal
	 * @param VoterSubject $subject
	 * @param list<string> $actions
	 * @param array &$scratchpad
	 * @return array<string, Criterion> Criterion by action
	 */
	private function getCriteriaSinglePrincipalSingleSubject(Principal $principal, VoterSubject $subject, array $actions, array &$scratchpad): array {
		/** @var array<string, Criterion> */
		$result = [];

		$remainingActions = $actions;

		$principalKey = $principal->getKey();
		$subjectKey = $subject->getVoterSubjectKey();

		$getCurrentCriterion = function (Principal $fPrincipal, VoterSubject $fSubject, string $fAction) use ($principal, $subject, &$result) {
			if($fPrincipal === $principal && $fSubject === $subject) {
				return $result[$fAction] ?? null;
			}
			
			return null;
		};

		foreach ($this->registry->getVotersForSubjectType($subject::VOTER_SUBJECT_TYPE) as $voter) {
			if(empty($remainingActions)) {
				break;
			}

			$voterDecisionByAction = $voter->vote(
				[$principal],
				[$subject],
				$remainingActions,
				$scratchpad,
				$getCurrentCriterion,
			)[$principalKey][$subjectKey] ?? [];	


			$finalActions = [];

			foreach($remainingActions as $action) {
				$voterDecision = $voterDecisionByAction[$action] ?? null;

				if($voterDecision instanceof VoterDecision) {
					$result[$action] = $voterDecision->criterion;

					if($voterDecision->final) {
						$finalActions[] = $action;
					}
				}
			}

			if(count($finalActions) !== 0) {
				$remainingActions = array_diff($remainingActions, $finalActions);
			}
		}

		foreach($remainingActions as $action) {
			if(!isset($result[$action])) {
				$result[$action] = new AlwaysUnsatisfiedCriterion("builtin:noVoterVoted");
			}
		}

		return $result;
	}

/**
	 * optimized version of getCriteria() for a single principal-subject-action combination
	 * 
	 * @return Criterion
	 */
	private function getCriterion(Principal $principal, VoterSubject $subject, string $action, array &$scratchpad): Criterion {
		$result = null;

		$principalKey = $principal->getKey();
		$subjectKey = $subject->getVoterSubjectKey();

		$getCurrentCriterion = function (Principal $fPrincipal, VoterSubject $fSubject, string $fAction) use ($principal, $subject, $action, &$result) {
			if($fPrincipal === $principal && $fSubject === $subject && $fAction === $action) {
				return $result;
			}
			
			return null;
		};

		foreach ($this->registry->getVotersForSubjectType($subject::VOTER_SUBJECT_TYPE) as $voter) {
			$voterDecision = $voter->vote(
				[$principal],
				[$subject],
				[$action],
				$scratchpad,
				$getCurrentCriterion,
			)[$principalKey][$subjectKey][$action] ?? null;	

			if($voterDecision instanceof VoterDecision) {
				$result = $voterDecision->criterion;

				if($voterDecision->final) {
					return $result;
				}
			}
		}

		return $result ?? new AlwaysUnsatisfiedCriterion("builtin:noVoterVoted");
	}
}
