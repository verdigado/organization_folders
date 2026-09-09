<?php

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin;

use Closure;

use OCP\IL10N;

use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\GlobalAdminCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderAdminCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderAnySubResourceManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\VoterSubject;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Model\Criterion\AnyCriterion;
use OCA\OrganizationFolders\Model\VoterDecision;
use OCA\OrganizationFolders\Service\ResourceService;

class BuiltinOrganizationFolderVoter extends BuiltinVoter {
	public function __construct(
		IL10N $l10n,
		OrganizationFolderMemberService $organizationFolderMemberService,
		ResourceService $resourceService,
		ResourceMemberService $resourceMemberService,
	) {
		parent::__construct($l10n, $organizationFolderMemberService, $resourceService, $resourceMemberService);
	}

	private const CRITERIA_GROUP_ADMIN_ONLY = 0;

	private const CRITERIA_GROUP_ADMIN_OR_MANAGER = 1;

	private const CRITERIA_GROUP_ADMIN_OR_MANAGER_OR_SUBRESOURCE_MANAGER = 2;

	private const ACTION_CRITERIA_GROUP = [
		"READ" => self::CRITERIA_GROUP_ADMIN_ONLY,
		"READ_LIMITED" => self::CRITERIA_GROUP_ADMIN_OR_MANAGER_OR_SUBRESOURCE_MANAGER,
		"UPDATE" => self::CRITERIA_GROUP_ADMIN_ONLY,
		"DELETE" => self::CRITERIA_GROUP_ADMIN_ONLY,
		"UPDATE_MEMBERS" => self::CRITERIA_GROUP_ADMIN_ONLY,
		"CREATE_TOP_LEVEL_RESOURCE" => self::CRITERIA_GROUP_ADMIN_OR_MANAGER,
	];

	private const ACTION_DECISION_FINAL = [
		"READ" => true,
		"READ_LIMITED" => true,
	];

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

			foreach($subjects as $subject) {
				$subjectKey = $subject->getVoterSubjectKey();
				$result[$principalKey][$subjectKey] = [];

				if($subject instanceof OrganizationFolder) {
					$currentActions = $actionsByPrincipalAndSubject($principal, $subject);
					if(count($currentActions) === 0) {
						continue;
					}
					
					$organizationFolderId = $subject->getId();

					$globalAdminCriterion = $scratchpad["criterionFactories"][GlobalAdminCriterion::CRITERION_TYPE]->build($principal);
					$organizationFolderAdminCriterion = $scratchpad["criterionFactories"][OrganizationFolderAdminCriterion::CRITERION_TYPE]->build($principal, $organizationFolderId);
					$organizationFolderManagerCriterion = $scratchpad["criterionFactories"][OrganizationFoldermanagerCriterion::CRITERION_TYPE]->build($principal, $organizationFolderId);
					$organizationFolderAnySubResourceManagerCriterion = $scratchpad["criterionFactories"][OrganizationFolderAnySubResourceManagerCriterion::CRITERION_TYPE]->build($principal, $organizationFolderId);
					
					$criteriaGroups = [
						self::CRITERIA_GROUP_ADMIN_ONLY => new AnyCriterion([
							$globalAdminCriterion,
							$organizationFolderAdminCriterion,
						]),
						self::CRITERIA_GROUP_ADMIN_OR_MANAGER => new AnyCriterion([
							$globalAdminCriterion,
							$organizationFolderManagerCriterion,
							$organizationFolderAdminCriterion,
						]),
						self::CRITERIA_GROUP_ADMIN_OR_MANAGER_OR_SUBRESOURCE_MANAGER => new AnyCriterion([
							$globalAdminCriterion,
							$organizationFolderManagerCriterion,
							$organizationFolderAdminCriterion,
							$organizationFolderAnySubResourceManagerCriterion,
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
