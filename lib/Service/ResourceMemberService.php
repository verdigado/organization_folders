<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\IGroupManager;
use OCP\IGroup;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\IEventDispatcher;

use OCA\OrganizationFolders\Errors\Api\ResourceMemberNotFound;
use OCA\OrganizationFolders\Errors\Api\PrincipalAlreadyResourceMember;
use OCA\OrganizationFolders\Errors\Api\ActionCancelled;

use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Db\ResourceMemberMapper;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\UserPrincipal;
use OCA\OrganizationFolders\Events\BeforeResourceMemberCreatedEvent;
use OCA\OrganizationFolders\Events\BeforeResourceMemberUpdatedEvent;
use OCA\OrganizationFolders\Events\BeforeResourceMemberDeletedEvent;

class ResourceMemberService extends AMemberService {
	public function __construct(
		protected readonly IGroupManager $groupManager,
		protected readonly IUserManager $userManager,
        protected readonly ResourceMemberMapper $mapper,
		protected readonly ResourceService $resourceService,
		protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly IEventDispatcher $eventDispatcher,
		protected readonly IUserSession $userSession,
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

	/**
	 * Get all members grouped by their permission level (0: members, 1: managers)
	 * @param int $resourceId
	 * @param array{principalType: PrincipalType} $filters
	 * @return array
	 * @psalm-return array{0: ResourceMember[], 1: ResourceMember[]}
	 */
	public function findAllByPermissionLevel(int $resourceId, $filters = []): array {
		$members = $this->findAll($resourceId, $filters);

		$result = [[], []];

		foreach($members as $member) {
			$result[$member->getPermissionLevel() - 1][] = $member;
		}

		return $result;
	}

	/**
	 * @param int $organizationFolderId
	 * @param array{principalType: PrincipalType} $filters
	 * @return array
	 * @psalm-return ResourceMember[]
	 */
	public function findAllTopLevelResourcesMembersOfOrganizationFolder(int $organizationFolderId, array $filters = []) {
		$mapperFilters = [
			"principalType" => $filters['principalType']?->value ?? null,
        ];

		return $this->mapper->findAllTopLevelResourcesMembersOfOrganizationFolder($organizationFolderId, $mapperFilters);
	}

	public function isUserIndividualMemberOfTopLevelResourceOfOrganizationFolder(int $organizationFolderId, string $userId): bool {
		return $this->mapper->isUserIndividualMemberOfTopLevelResourceOfOrganizationFolder($organizationFolderId, $userId);
	}

	public function getIdsOfOrganizationFoldersUserIsTopLevelResourceIndividualMemberIn(string $userId): array {
		return $this->mapper->getIdsOfOrganizationFoldersUserIsTopLevelResourceIndividualMemberIn($userId);
	}

	public function countOrganizationFolderTopLevelResourceIndividualMembers(int $organizationFolderId): int {
		return $this->mapper->countOrganizationFolderTopLevelResourceIndividualMembers($organizationFolderId);
	}

	public function hasOrganizationFolderTopLevelResourceIndividualMembers(int $organizationFolderId): bool {
		return $this->mapper->hasOrganizationFolderTopLevelResourceIndividualMembers($organizationFolderId);
	}

	public function getIdsOfOrganizationFoldersWithTopLevelResourceIndividualMembers(): array {
		return $this->mapper->getIdsOfOrganizationFoldersWithTopLevelResourceIndividualMembers();
	}

	public function getUserIdsOfOrganizationFolderTopLevelResourceIndividualMembers(int $organizationFolderId, ?int $limit = null, int $offset = 0): array {
		return $this->mapper->getUserIdsOfOrganizationFolderTopLevelResourceIndividualMembers($organizationFolderId, $limit, $offset);
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
		$principalType = $principal->getType()->value;
		$principalId = $principal->getId();
		
		try {
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
			throw new PrincipalAlreadyResourceMember($principal, $resource);
		}

		$member = new ResourceMember();

		$member->setResourceId($resource->getId());
		$member->setPermissionLevel($permissionLevel->value);
		$member->setPrincipal($principal);

		$creationTime = time();
        $member->setCreatedTimestamp($creationTime);
        $member->setLastUpdatedTimestamp($creationTime);

        // emit cancellable event before doing any changes
        $beforeEvent = new BeforeResourceMemberCreatedEvent(
            $member,
            $resource,
            $this->userSession->getUser()?->getUID(),
        );
        $this->eventDispatcher->dispatchTyped($beforeEvent);
        if ($beforeEvent->isCancelled()) {
            throw new ActionCancelled(
                $beforeEvent->getErrorMessage(),
            );
        }

		$member = $this->mapper->insert($member);

		if(!$skipPermssionsApply) {
			$this->organizationFolderService->applyAllPermissionsById($resource->getOrganizationFolderId());
		}

		return $member;
	}

	public function update(int $id, ?ResourceMemberPermissionLevel $permissionLevel = null, ?Principal $principal = null): ResourceMember {
		try {
			$member = $this->mapper->find($id);
			$resource = $this->resourceService->find($member->getResourceId());

			// emit cancellable event before doing any changes
			$beforeEvent = new BeforeResourceMemberUpdatedEvent(
				$member,
				$resource,
				$this->userSession->getUser()?->getUID(),
				$permissionLevel,
				$principal,
			);
			$this->eventDispatcher->dispatchTyped($beforeEvent);
			if ($beforeEvent->isCancelled()) {
				throw new ActionCancelled(
					$beforeEvent->getErrorMessage(),
				);
			}

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

			$this->organizationFolderService->applyAllPermissionsById($resource->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e, ["id" => $id]);
		}
	}

	public function delete(int $id): ResourceMember {
		try {
			$member = $this->mapper->find($id);
			$resource = $this->resourceService->find($member->getResourceId());

			// emit cancellable event before doing any changes
			$beforeEvent = new BeforeResourceMemberDeletedEvent(
				$member,
				$resource,
				$this->userSession->getUser()?->getUID(),
			);
			$this->eventDispatcher->dispatchTyped($beforeEvent);
			if ($beforeEvent->isCancelled()) {
				throw new ActionCancelled(
					$beforeEvent->getErrorMessage(),
				);
			}

			$this->mapper->delete($member);
			$this->organizationFolderService->applyAllPermissionsById($resource->getOrganizationFolderId());

			return $member;
		} catch (Exception $e) {
			$this->handleException($e, ["id" => $id]);
		}
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

		return $this->groupDiff($results, $existingPrincipals);
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

		return $this->userDiff($results, $existingPrincipals);
	}
}
