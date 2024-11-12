<?php

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\OrganizationFolders\Errors\ResourceMemberNotFound;

use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Db\ResourceMemberMapper;

use OCA\OrganizationFolders\Enum\MemberPermissionLevel;
use OCA\OrganizationFolders\Model\Principal;

class ResourceMemberService {
	public function __construct(
        protected ResourceMemberMapper $mapper,
		protected ResourceService $resourceService,
		protected OrganizationFolderService $organizationFolderService,
    ) {
	}

	/**
	 * @param int $resourceId
	 * @psalm-param int $resourceId
	 * @return array
	 * @psalm-return ResourceMember[]
	 */
	public function findAll(int $resourceId): array {
		return $this->mapper->findAll($resourceId);
	}

	private function handleException(Exception $e): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new ResourceMemberNotFound($e->getMessage());
		} else {
			throw $e;
		}
	}

	public function find(int $id): ResourceMember {
		try {
			return $this->mapper->find($id);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function create(
		int $resourceId,
		MemberPermissionLevel $permissionLevel,
		Principal $principal,
	): ResourceMember {
		$resource = $this->resourceService->find($resourceId);

		$member = new ResourceMember();

		$member->setResourceId($resource->getId());
		$member->setPermissionLevel($permissionLevel->value);
		$member->setPrincipal($principal);
        $member->setCreatedTimestamp(time());
        $member->setLastUpdatedTimestamp(time());

		$member = $this->mapper->insert($member);

		$this->organizationFolderService->applyPermissions($resource->getOrganizationFolderId());

		return $member;
	}

	public function update(int $id, ?MemberPermissionLevel $permissionLevel = null, ?Principal $principal = null): ResourceMember {
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
            }

			$member = $this->mapper->update($member);

			$resource = $this->resourceService->find($member->getResourceId());
			$this->organizationFolderService->applyPermissions($resource->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function delete(int $id): ResourceMember {
		try {
			$member = $this->mapper->find($id);

			$this->mapper->delete($member);

			$resource = $this->resourceService->find($member->getResourceId());
			$this->organizationFolderService->applyPermissions($resource->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}