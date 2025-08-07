<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Manager;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\Folder\FolderManager;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\AclList;

class ACLManager {
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
	 * @return array{created: Rule[], removed: Rule[], updated: Rule[]}
	 */
	public function overwriteACLsForFileId(int $fileId, array $rules): array {
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
		$newRules = array_udiff($rules, $existingRules, $this->ruleMappingComparison(...));

		// old rules to be deleted
		/** @var Rule[] */
		$removedRules = array_udiff($existingRules, $rules, $this->ruleMappingComparison(...));

		// rules for user or group for which a rule already exists, but it might need to be updated
		/** @var Rule[] */
		$potentiallyUpdatedRules = array_uintersect($rules, $existingRules, $this->ruleMappingComparison(...));


		foreach($removedRules as $removedRule) {
			$this->ruleManager->deleteRule($removedRule);
		}

		foreach($newRules as $newRule) {
			$this->ruleManager->saveRule($newRule);
		}

		$updatedRules = [];

		foreach($potentiallyUpdatedRules as $potentiallyUpdatedRule) {
			$key = $potentiallyUpdatedRule->getUserMapping()->getKey();

			if($potentiallyUpdatedRule->getMask() !== $existingMasks[$key] || $potentiallyUpdatedRule->getPermissions() !== $existingPermissions[$key]) {
				$this->ruleManager->saveRule($potentiallyUpdatedRule);
				$updatedRules[] = $potentiallyUpdatedRule;
			}
		}

		return ["created" => $newRules, "removed" => $removedRules, "updated" => $updatedRules];
	}

	/**
	 * @param AclList $aclList
	 * @return array{created: Rule[], removed: Rule[], updated: Rule[]}
	 */
	public function overwriteACLs(AclList $aclList) {
		return $this->overwriteACLsForFileId($aclList->getFileId(), $aclList->getRules());
	}
}
