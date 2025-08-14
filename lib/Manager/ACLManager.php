<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Manager;

use OCP\IDBConnection;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\Folder\FolderManager;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\AclList;
use OCA\OrganizationFolders\Model\GroupfolderACLsUpdatePlan;

class ACLManager {
	use TTransactional;

	public function __construct(
		protected IDBConnection $db,
		protected FolderManager $folderManager,
		protected RuleManager $ruleManager,
		protected OrganizationProviderManager $organizationProviderManager
	) {
	}

	protected function createRuleEntityFromRow(array $data): Rule {
		return new Rule(
			new UserMapping(type: $data['mapping_type'], id: $data['mapping_id'], displayName: null),
			(int)$data['fileid'],
			(int)$data['mask'],
			(int)$data['permissions']
		);
	}

	/**
	 * @param int $fileId
	 * @return Rule[]
	 */
	public function getAllRulesForFileId(int $fileId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select(['fileid', 'mapping_type', 'mapping_id', 'mask', 'permissions'])
			->from('group_folders_acl')
			->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

		$rows = $qb->executeQuery()->fetchAll();

		return array_map($this->createRuleEntityFromRow(...), $rows);
	}

	public function createAclRuleForPrincipal(Principal $principal, int $fileId, int $mask, int $permissions): ?Rule {
		$mapping = $principal->toGroupfolderAclMapping();

		if(is_null($mapping)) {
			return null;
		}

		return new Rule(
			userMapping: $mapping,
			fileId: $fileId,
			mask: $mask,
			permissions: $permissions,
		);
	}

	protected function ruleMappingComparison(Rule $rule1, Rule $rule2): int {
		$mapping1 = $rule1->getUserMapping();
		$mapping2 = $rule2->getUserMapping();

		return $mapping1->getType() <=> $mapping2->getType() ?: $mapping1->getId() <=> $mapping2->getId();
	}

	/**
	 * @param int $fileId
	 * @param Rule[] $rules
	 * @return GroupfolderACLsUpdatePlan
	 */
	public function createUpdatePlan(int $fileId, array $rules): GroupfolderACLsUpdatePlan {
		$existingRules = $this->getAllRulesForFileId($fileId);

		$existingMasks = [];
		$existingPermissions = [];

		foreach($existingRules as $existingRule) {
			$key = $existingRule->getUserMapping()->getKey();
			$existingMasks[$key] = $existingRule->getMask();
			$existingPermissions[$key] = $existingRule->getPermissions();
		}

		// TODO: Investigate if sorting rules arrays first increases performance, because the internal sorting in the diff function is probably faster if presorted

		// new rules to be added
		/** @var Rule[] */
		$rulesToCreate = array_udiff($rules, $existingRules, $this->ruleMappingComparison(...));

		// old rules to be deleted
		/** @var Rule[] */
		$rulesToRemove = array_udiff($existingRules, $rules, $this->ruleMappingComparison(...));

		// rules for user or group for which a rule already exists, but it might need to be updated
		/** @var Rule[] */
		$rulesToPotentiallyUpdate = array_uintersect($rules, $existingRules, $this->ruleMappingComparison(...));

		// rules that actually need to be updated
		/** @var Rule[] */
		$rulesToUpdate = [];

		foreach($rulesToPotentiallyUpdate as $ruleToPotentiallyUpdate) {
			$key = $ruleToPotentiallyUpdate->getUserMapping()->getKey();

			if($ruleToPotentiallyUpdate->getMask() !== $existingMasks[$key] || $ruleToPotentiallyUpdate->getPermissions() !== $existingPermissions[$key]) {
				$rulesToUpdate[] = $ruleToPotentiallyUpdate;
			}
		}

		return new GroupfolderACLsUpdatePlan(toCreate: $rulesToCreate, toUpdate: $rulesToUpdate, toRemove: $rulesToRemove);
	}

	public function applyUpdatePlan(GroupfolderACLsUpdatePlan $plan): void {
		$this->atomic(function () use ($plan) {
			foreach($plan->toRemove as $removedRule) {
				$this->ruleManager->deleteRule($removedRule);
			}

			foreach($plan->toCreate as $newRule) {
				$this->ruleManager->saveRule($newRule);
			}

			foreach($plan->toUpdate as $updatedRule) {
				$this->ruleManager->saveRule($updatedRule);
			}
		}, $this->db);
	}

	/**
	 * @param int $fileId
	 * @param Rule[] $rules
	 * @return GroupfolderACLsUpdatePlan
	 */
	public function overwriteACLs(int $fileId, array $rules): GroupfolderACLsUpdatePlan {
		$updatePlan = $this->createUpdatePlan($fileId, $rules);
		$this->applyUpdatePlan($updatePlan);

		return $updatePlan;
	}

	public function createUpdatePlanFromAclList(AclList $aclList): GroupfolderACLsUpdatePlan {
		return $this->createUpdatePlan($aclList->getFileId(), $aclList->getRules());
	}

	public function overwriteACLsFromAclList(AclList $aclList): GroupfolderACLsUpdatePlan {
		$updatePlan = $this->createUpdatePlanFromAclList($aclList);
		$this->applyUpdatePlan($updatePlan);

		return $updatePlan;
	}
}
