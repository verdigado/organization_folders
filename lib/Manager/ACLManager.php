<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Manager;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\Folder\FolderManager;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Enum\PrincipalType;

class ACLManager {
    public function __construct(
        protected IDBConnection $db,
		protected FolderManager $folderManager,
        protected IUserMappingManager $userMappingManager,
        protected RuleManager $ruleManager,
        protected OrganizationProviderManager $organizationProviderManager
	) {
    }

    protected function createRuleEntityFromRow(array $data): ?Rule {
		$mapping = $this->userMappingManager->mappingFromId($data['mapping_type'], $data['mapping_id']);

		if ($mapping) {
			return new Rule(
				$mapping,
				(int)$data['fileid'],
				(int)$data['mask'],
				(int)$data['permissions']
			);
		} else {
			return null;
		}
	}

    public function getAllRulesForFileId(int $fileId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select(['fileid', 'mapping_type', 'mapping_id', 'mask', 'permissions'])
			->from('group_folders_acl')
			->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

		$rows = $qb->executeQuery()->fetchAll();

        return array_map($this->createRuleEntityFromRow(...), $rows);
    }

    public function getMappingForPrincipal(Principal $principal): IUserMapping {
        if($principal->getType() === PrincipalType::USER) {
            return $this->userMappingManager->mappingFromId("user", $principal->getId());
        } else if($principal->getType() === PrincipalType::GROUP) {
            return $this->userMappingManager->mappingFromId("group", $principal->getId());
        } else if($principal->getType() === PrincipalType::ROLE) {
            [$organizationProviderId, $roleId] = explode(":", $principal->getId(), 2);

            $organizationProvider = $this->organizationProviderManager->getOrganizationProvider($organizationProviderId);
            $role = $organizationProvider->getRole($roleId);

            return $this->userMappingManager->mappingFromId("group", $role->getMembersGroup());
        } else {
            throw new \Exception("invalid resource member type");
        }
    }

    public function createAclRuleForPrincipal(Principal $principal, int $fileId, int $mask, int $permissions): ?Rule {
        $mapping = $this->getMappingForPrincipal($principal);

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

    public function overwriteACLsForFileId(int $fileId, array $rules): array {
        $existingRules = $this->getAllRulesForFileId($fileId);

        // new rules to be added
        $newRules = array_udiff($rules, $existingRules, $this->ruleMappingComparison(...));

        // old rules to be deleted
        $removedRules = array_udiff($existingRules, $rules, $this->ruleMappingComparison(...));

        // rules for user or group for which a rule already exists, but it might need to be updated
        $potentiallyUpdatedRules = array_uintersect($rules, $existingRules, $this->ruleMappingComparison(...));


        foreach($removedRules as $removedRule) {
            $this->ruleManager->deleteRule($removedRule);
        }

        foreach($newRules as $newRule) {
            $this->ruleManager->saveRule($newRule);
        }

        foreach($potentiallyUpdatedRules as $potentiallyUpdatedRule) {
            $this->ruleManager->saveRule($potentiallyUpdatedRule);
        }

        return ["created" => $newRules, "removed" => $removedRules, "updated" => $potentiallyUpdatedRules];
    }

}
