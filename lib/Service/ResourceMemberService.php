<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\IGroupManager;
use OCP\IGroup;
use OCP\IUserManager;
use OCP\IUser;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\OrganizationFolders\Errors\ResourceMemberNotFound;
use OCA\OrganizationFolders\Errors\PrincipalAlreadyResourceMember;

use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Db\ResourceMemberMapper;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\UserPrincipal;

class ResourceMemberService {
	public function __construct(
		protected IGroupManager $groupManager,
		protected IUserManager $userManager,
        protected ResourceMemberMapper $mapper,
		protected ResourceService $resourceService,
		protected OrganizationFolderService $organizationFolderService,
    ) {
	}

	/**
	 * @param int $resourceId
	 * @param array{permissionLevel: ResourceMemberPermissionLevel, principalType: PrincipalType} $filters
	 * @return array
	 * @psalm-return ResourceMember[]
	 */
	public function findAll(int $resourceId, $filters = []): array {
		$mapperFilters = [
			"permissionLevel" => $filters['permissionLevel']?->value ?? null,
			"principalType" => $filters['principalType']?->value ?? null,
        ];

		return $this->mapper->findAll($resourceId, $mapperFilters);
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

	/**
	 * @param IGroup|GroupPrincipal object1
	 * @param IGroup|GroupPrincipal object2
	 */
	protected function iGroupGroupPrincipalComparison($object1, $object2): int {
		$value1 = method_exists($object1, "getGID") ? $object1?->getGID() : $object1?->getId();
		$value2 = method_exists($object2, "getGID") ? $object2?->getGID() : $object2?->getId();

		return $value1 <=> $value2;
	}

	/**
	 * @return IGroup[]
	 */
	public function findGroupMemberOptions(int $resourceId, string $search = '', ?int $limit = null): array {
		$results = $this->groupManager->search($search, $limit);

		$existingMembers = $this->findAll($resourceId, [
			"principalType" => PrincipalType::GROUP,
		]);

		$existingPrincipals = array_map(fn($member): GroupPrincipal => $member->getPrincipal(), $existingMembers);

		return array_values(array_udiff($results, $existingPrincipals, $this->iGroupGroupPrincipalComparison(...)));
	}

	/**
	 * @param IUser|UserPrincipal object1
	 * @param IUser|UserPrincipal object2
	 */
	 protected function iUserUserPrincipalComparison($object1, $object2): int {
		$value1 = method_exists($object1, "getUID") ? $object1?->getUID() : $object1?->getId();
		$value2 = method_exists($object2, "getUID") ? $object2?->getUID() : $object2?->getId();

		return $value1 <=> $value2;
	}

	/**
	 * @return IUser[]
	 */
	public function findUserMemberOptions(int $resourceId, string $search = '', ?int $limit = null): array {
		$results = $this->userManager->search($search, $limit);

		$existingMembers = $this->findAll($resourceId, [
			"principalType" => PrincipalType::USER,
		]);

		$existingPrincipals = array_map(fn($member): UserPrincipal => $member->getPrincipal(), $existingMembers);

		return array_values(array_udiff($results, $existingPrincipals, $this->iUserUserPrincipalComparison(...)));
	}
}