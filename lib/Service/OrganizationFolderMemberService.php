<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\IGroupManager;
use OCP\IGroup;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\OrganizationFolders\Errors\Api\OrganizationFolderMemberNotFound;
use OCA\OrganizationFolders\Errors\Api\PrincipalAlreadyOrganizationFolderMember;
use OCA\OrganizationFolders\Errors\Api\PrincipalInvalid;

use OCA\OrganizationFolders\Db\OrganizationFolderMember;
use OCA\OrganizationFolders\Db\OrganizationFolderMemberMapper;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\PrincipalFilter;

class OrganizationFolderMemberService extends AMemberService {
	public function __construct(
		protected readonly IGroupManager $groupManager,
        protected readonly OrganizationFolderMemberMapper $mapper,
		protected readonly OrganizationFolderService $organizationFolderService,
    ) {
	}

	/**
	 * @param int $organizationFolderId
     * @param array{
	 * 	 permissionLevel: ?OrganizationFolderMemberPermissionLevel[],
	 * 	 principal: ?PrincipalFilter[]
	 * } $filters
	 * @return array
	 * @psalm-return OrganizationFolderMember[]
	 */
	public function findAll(int $organizationFolderId, array $filters = []): array {
		return $this->mapper->findAll($organizationFolderId, $filters);
	}

    /**
    * @throws OrganizationFolderMemberNotFound
    */

	private function handleException(Exception $e): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new OrganizationFolderMemberNotFound($e->getMessage());
		} else {
			throw $e;
		}
	}

    /** 
     * @param int $id
     * @psalm-param int $id
     * @throws OrganizationFolderMemberNotFound
     */
	public function find(int $id): OrganizationFolderMember {
		try {
			return $this->mapper->find($id);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * @param int $organizationFolderId
     * @param array{
	 * 	permissionLevel: OrganizationFolderMemberPermissionLevel[],
	 * 	principal: PrincipalFilter[]
	 * } $filters
	 * @return int
	 */
	public function count(int $organizationFolderId, array $filters = []): int {
		return $this->mapper->count($organizationFolderId, $filters);
	}

	public function create(
		OrganizationFolder $organizationFolder,
		OrganizationFolderMemberPermissionLevel $permissionLevel,
		Principal $principal,
	): OrganizationFolderMember {
        if(!$principal->isValid()) {
            throw new PrincipalInvalid($principal);
        }

        if($this->mapper->exists(
            organizationFolderId: $organizationFolder->getId(),
            principalType: $principal->getType()->value,
            principalId: $principal->getId()
        )) {
			throw new PrincipalAlreadyOrganizationFolderMember($principal, $organizationFolder);
		}

		$member = new OrganizationFolderMember();

		$member->setOrganizationFolderId($organizationFolder->getId());
		$member->setPermissionLevel($permissionLevel->value);
		$member->setPrincipal($principal);

        $creationTime = time();
        $member->setCreatedTimestamp($creationTime);
        $member->setLastUpdatedTimestamp($creationTime);

		$member = $this->mapper->insert($member);

		$this->organizationFolderService->applyAllPermissionsById($organizationFolder->getId());

		return $member;
	}

	public function update(int $id, ?OrganizationFolderMemberPermissionLevel $permissionLevel = null, ?Principal $principal = null): OrganizationFolderMember {
		try {
			$member = $this->mapper->find($id);

            if(isset($permissionLevel)) {
                $member->setPermissionLevel($permissionLevel->value);
            }

            if(isset($principal)) {
                if(!$principal->isValid()) {
                    throw new PrincipalInvalid($principal);
                }
                
				$member->setPrincipal($principal);
            }
			
            if(count($member->getUpdatedFields()) > 0) {
                $member->setLastUpdatedTimestamp(time());

                $member = $this->mapper->update($member);
            }

			$this->organizationFolderService->applyAllPermissionsById($member->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function delete(int $id): OrganizationFolderMember {
		try {
			$member = $this->mapper->find($id);

			$this->mapper->delete($member);

			$this->organizationFolderService->applyAllPermissionsById($member->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * @return IGroup[]
	 */
	public function findGroupMemberOptions(int $organizationFolderId, string $search = '', ?int $limit = null): array {
		// TODO: currently less groups than $limit can be returned, because some are filtered out with groupDiff
		$results = $this->groupManager->search($search, $limit);

		$existingMembers = $this->findAll($organizationFolderId, [
			"principal" => [new PrincipalFilter(PrincipalType::GROUP)],
		]);

		$existingPrincipals = array_map(fn($member): GroupPrincipal => $member->getPrincipal(), $existingMembers);

		return $this->groupDiff($results, $existingPrincipals);
	}
}