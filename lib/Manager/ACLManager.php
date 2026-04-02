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

/**
 * @psalm-type RuleRow = array{
 *   mapping_type: string,
 *   mapping_id: string,
 *   mask: int,
 *   permissions: int,
 * }
 */
class ACLManager {
	use TTransactional;

	public function __construct(
		protected IDBConnection $db,
		protected FolderManager $folderManager,
		protected RuleManager $ruleManager,
		protected OrganizationProviderManager $organizationProviderManager
	) {
	}

	/**
	 * @param int $fileId
	 * @psalm-param RuleRow $row
	 * @return Rule
	 */
	protected function createRuleEntityFromRow(int $fileId, array $row): Rule {
		return new Rule(
			new UserMapping(type: $row['mapping_type'], id: $row['mapping_id'], displayName: null),
			$fileId,
			(int)$row['mask'],
			(int)$row['permissions']
		);
	}

	/**
	 * @param int $fileId
	 * @psalm-return RuleRow[]
	 */
	public function getAllRuleRowsForFileId(int $fileId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select(['mapping_type', 'mapping_id', 'mask', 'permissions'])
			->from('group_folders_acl')
			->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

		return $qb->executeQuery()->fetchAll();
	}

	/**
	 * @param int $fileId
	 * @return Rule[]
	 */
	public function getAllRulesForFileId(int $fileId) {
		return array_map(fn($row) => $this->createRuleEntityFromRow($fileId, $row), $this->getAllRuleRowsForFileId($fileId));
	}

	/**
	 * @param int $fileId
	 * @param Rule[] $rules
	 * @return GroupfolderACLsUpdatePlan
	 */
	public function createUpdatePlan(int $fileId, array $rules): GroupfolderACLsUpdatePlan {
		$existingRuleRows = $this->getAllRuleRowsForFileId($fileId);

		/** @var array{
		 *   user: array<string, RuleRow>,
		 *   group: array<string, RuleRow>,
		 *   circle: array<string, RuleRow>,
		 * }
		 */
		$existingRuleRowsByKey = [
			"user" => [],
			"group" => [],
			"circle" => [],
		];

		foreach($existingRuleRows as $existingRuleRow) {
			$existingRuleRowsByKey[$existingRuleRow["mapping_type"]][$existingRuleRow["mapping_id"]] = $existingRuleRow;
		}

		/** @var array{
		 *   user: array<string, Rule>,
		 *   group: array<string, Rule>,
		 *   circle: array<string, Rule>,
		 * }
		 */
		$rulesByKey = [
			"user" => [],
			"group" => [],
			"circle" => [],
		];

		/** @var Rule[] */
		$rulesToCreate = [];

		/** @var Rule[] */
		$rulesToUpdate = [];

		foreach($rules as $rule) {
			$mapping = $rule->getUserMapping();
			$mappingType = $mapping->getType();
			$mappingId = $mapping->getId();

			$rulesByKey[$mappingType][$mappingId] = $rule;

			$existingRuleRow = $existingRuleRowsByKey[$mappingType][$mappingId] ?? null;

			if($existingRuleRow === null) {
				$rulesToCreate[] = $rule;
			} else {
				if($existingRuleRow['mask'] !== $rule->getMask() || $existingRuleRow['permissions'] !== $rule->getPermissions()) {
					$rulesToUpdate[] = $rule;
				}
			}
		}

		/** @var Rule[] */
		$rulesToRemove = [];

		foreach($existingRuleRows as $existingRuleRow) {
			if (!isset($rulesByKey[$existingRuleRow['mapping_type']][$existingRuleRow['mapping_id']])) {
        		$rulesToRemove[] = $this->createRuleEntityFromRow($fileId, $existingRuleRow);
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
