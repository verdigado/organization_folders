<?php

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\OrganizationFolders\Errors\ResourceMemberNotFound;
use OCA\OrganizationFolders\Errors\PrincipalAlreadyResourceMember;

use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Db\ResourceMemberMapper;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
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

	private function handleException(Exception $e, array $criteria): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new ResourceMemberNotFound($criteria);
		} else {
			throw $e;
		}
	}

	public function find(int $id): ResourceMember {
		try {
			return $this->mapper->find($id);
		} catch (Exception $e) {
			$this->handleException($e, ["id" => $id]);
		}
	}

	public function findByPrincipal(int $resourceId, Principal $principal): ResourceMember {
		try {
			$principalType = $principal->getType()->value;
			$principalId = $principal->getId();

			return $this->mapper->findByPrincipal($resourceId, $principalType, $principalId);
		} catch (Exception $e) {
			$this->handleException($e, ["resourceId" => $resourceId, "principalType" => $principalType, "principalId" => $principalId]);
		}
	}

	public function create(
		int $resourceId,
		ResourceMemberPermissionLevel $permissionLevel,
		Principal $principal,

		bool $skipPermssionsApply = false
	): ResourceMember {
		$resource = $this->resourceService->find($resourceId);

		if($this->mapper->exists($resourceId, $principal->getType()->value, $principal->getId())) {
			throw new PrincipalAlreadyResourceMember($principal, $resourceId);
		}

		$member = new ResourceMember();

		$member->setResourceId($resource->getId());
		$member->setPermissionLevel($permissionLevel->value);
		$member->setPrincipal($principal);

		$creationTime = time();
        $member->setCreatedTimestamp($creationTime);
        $member->setLastUpdatedTimestamp($creationTime);

		$member = $this->mapper->insert($member);

		if(!$skipPermssionsApply) {
			$this->organizationFolderService->applyPermissions($resource->getOrganizationFolderId());
		}

		return $member;
	}

	public function update(int $id, ?ResourceMemberPermissionLevel $permissionLevel = null, ?Principal $principal = null): ResourceMember {
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

			$resource = $this->resourceService->find($member->getResourceId());
			$this->organizationFolderService->applyPermissions($resource->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e, $id);
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
			$this->handleException($e, $id);
		}
	}
}