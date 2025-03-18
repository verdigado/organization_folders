<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\OrganizationFolders\Errors\OrganizationFolderMemberNotFound;

use OCA\OrganizationFolders\Db\OrganizationFolderMember;
use OCA\OrganizationFolders\Db\OrganizationFolderMemberMapper;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\Principal;

class OrganizationFolderMemberService {
	public function __construct(
        protected OrganizationFolderMemberMapper $mapper,
		protected OrganizationFolderService $organizationFolderService,
    ) {
	}

	/**
	 * @param int $organizationFolderId
     * @param array{permissionLevel: OrganizationFolderMemberPermissionLevel, principalType: PrincipalType} $filters
	 * @return array
	 * @psalm-return OrganizationFolderMember[]
	 */
	public function findAll(int $organizationFolderId, $filters = []): array {
        $mapperFilters = [
            "permissionLevel" => $filters['permissionLevel']?->value ?? null,
            "principalType" => $filters['principalType']?->value ?? null,
        ];

		return $this->mapper->findAll($organizationFolderId, $mapperFilters);
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

	public function create(
		int $organizationFolderId,
		OrganizationFolderMemberPermissionLevel $permissionLevel,
		Principal $principal,
	): OrganizationFolderMember {
		$organizationFolder = $this->organizationFolderService->find($organizationFolderId);

		$member = new OrganizationFolderMember();

		$member->setOrganizationFolderId($organizationFolder->getId());
		$member->setPermissionLevel($permissionLevel->value);
		$member->setPrincipal($principal);

        $creationTime = time();
        $member->setCreatedTimestamp($creationTime);
        $member->setLastUpdatedTimestamp($creationTime);

		$member = $this->mapper->insert($member);

		$this->organizationFolderService->applyPermissions($organizationFolder->getId());

		return $member;
	}

	public function update(int $id, ?OrganizationFolderMemberPermissionLevel $permissionLevel = null, ?Principal $principal = null): OrganizationFolderMember {
		try {
			$member = $this->mapper->find($id);

            if(isset($permissionLevel)) {
                $member->setPermissionLevel($permissionLevel->value);
            }

            if(isset($principal)) {
				$member->setPrincipal($principal);
            }
			
            if(count($member->getUpdatedFields()) > 0) {
                $member->setLastUpdatedTimestamp(time());

                $member = $this->mapper->update($member);
            }

			$this->organizationFolderService->applyPermissions($member->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function delete(int $id): OrganizationFolderMember {
		try {
			$member = $this->mapper->find($id);

			$this->mapper->delete($member);

			$this->organizationFolderService->applyPermissions($member->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}