<?php

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin;

use Closure;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\GlobalAdminCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderAdminCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\ResourceAnySubResourceManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\ResourceManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\VoterSubject;
use OCA\OrganizationFolders\Model\Criterion\AnyCriterion;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\VoterDecision;
use OCP\IL10N;

class BuiltinResourceVoter extends BuiltinVoter {

	private const CRITERIA_GROUP_MANAGER = 0;

	private const CRITERIA_GROUP_MANAGER_OR_SUBRESOURCE_MANAGER = 1;

	private const ACTION_CRITERIA_GROUP = [
		"READ" => self::CRITERIA_GROUP_MANAGER,
		"READ_LIMITED" => self::CRITERIA_GROUP_MANAGER_OR_SUBRESOURCE_MANAGER,
		"UPDATE" => self::CRITERIA_GROUP_MANAGER,
		"DELETE" => self::CRITERIA_GROUP_MANAGER,
		"UPDATE_MEMBERS" => self::CRITERIA_GROUP_MANAGER,
		"UPDATE_LINK_SHARES" => self::CRITERIA_GROUP_MANAGER,
		"CREATE_SUBRESOURCE" => self::CRITERIA_GROUP_MANAGER,
		"RESTORE_FROM_SNAPSHOT" => self::CRITERIA_GROUP_MANAGER,
	];

	private const ACTION_DECISION_FINAL = [
		"READ" => true,
		"READ_LIMITED" => true,
	];

	public function __construct(
		IL10N $l10n,
		OrganizationFolderMemberService $organizationFolderMemberService,
		ResourceService $resourceService,
		ResourceMemberService $resourceMemberService,
	) {
		parent::__construct($l10n, $organizationFolderMemberService, $resourceService, $resourceMemberService);
	}


	/**
	 * @param Principal[] $principals
	 * @param VoterSubject[] $subjects
	 * @param list<string>|Closure(Principal, VoterSubject): list<string> $actions
	 * @return array<string, array<string, array<string, VoterDecision>>>
	 */
	public function vote(array $principals, array $subjects, array|Closure $actions, array &$scratchpad): array {
		$this->registerCriterionFactories($scratchpad);

		
		if($actions instanceof Closure) {
			// already a closure
			$actionsByPrincipalAndSubject = $actions;
		} else {
			// static actions
			$actionsByPrincipalAndSubject = function (Principal $principal, VoterSubject $subject) use ($actions) {
				return $actions;
			};
		}

		$result = [];

		foreach($principals as $principal) {
			$principalKey = $principal->getKey();

			$result[$principalKey] = [];


			foreach($subjects as $subject) {
				$subjectKey = $subject->getVoterSubjectKey();
				$result[$principalKey][$subjectKey] = [];

				if($subject instanceof Resource) {
					$currentActions = $actionsByPrincipalAndSubject($principal, $subject);
					if(count($currentActions) === 0) {
						continue;
					}

					$resource = $subject;
					$resourceId = $resource->getId();
					$organizationFolderId = $resource->getOrganizationFolderId();

					$globalAdminCriterion = $scratchpad["criterionFactories"][GlobalAdminCriterion::CRITERION_TYPE]->build($principal);
					$organizationFolderAdminCriterion = $scratchpad["criterionFactories"][OrganizationFolderAdminCriterion::CRITERION_TYPE]->build($principal, $organizationFolderId);
					$resourceManagerCriterion = $scratchpad["criterionFactories"][ResourceManagerCriterion::CRITERION_TYPE]->buildFromId($principal, $resourceId);
					$anySubResourceManagerCriterion = $scratchpad["criterionFactories"][ResourceAnySubResourceManagerCriterion::CRITERION_TYPE]->build($principal, $resource);

					$criteriaGroups = [
						self::CRITERIA_GROUP_MANAGER => new AnyCriterion([
							$globalAdminCriterion,
							$resourceManagerCriterion,
							$organizationFolderAdminCriterion,
						]),
						self::CRITERIA_GROUP_MANAGER_OR_SUBRESOURCE_MANAGER => new AnyCriterion([
							$globalAdminCriterion,
							$resourceManagerCriterion,
							$organizationFolderAdminCriterion,
							$anySubResourceManagerCriterion,
						]),
					];

					foreach($currentActions as $action) {
						if(array_key_exists($action, self::ACTION_CRITERIA_GROUP)) {
							$result[$principalKey][$subjectKey][$action] = new VoterDecision($criteriaGroups[self::ACTION_CRITERIA_GROUP[$action]], self::ACTION_DECISION_FINAL[$action] ?? false);
						}
					}
				}

			}
		}

		return $result;
	}
}
