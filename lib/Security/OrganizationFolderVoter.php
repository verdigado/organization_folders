<?php

namespace OCA\OrganizationFolders\Security;

use OCA\OrganizationFolders\Model\PrincipalBackedByGroup;
use OCP\IUser;
use OCP\IGroupManager;

use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\OrganizationMemberPrincipal;
use OCA\OrganizationFolders\Model\OrganizationRolePrincipal;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class OrganizationFolderVoter extends Voter {
	public function __construct(
		private IGroupManager $groupManager,
		private OrganizationFolderMemberService $organizationFolderMemberService,
		private ResourceService $resourceService,
		private OrganizationProviderManager $organizationProviderManager,
	) {
	}
	protected function supports(string $attribute, mixed $subject): bool {
		return $subject instanceof OrganizationFolder || $subject === OrganizationFolder::class;
	}

	protected function voteOnAttribute(string $attribute, mixed $subject, ?IUser $user): bool {
		if (!$user) {
			return false;
		}

		/** @var OrganizationFolder */
		$organizationFolder = $subject;
		return match ($attribute) {
			// Admin permissions required
			'READ' => $this->isOrganizationFolderAdmin($user, $organizationFolder),
			'UPDATE' => $this->isOrganizationFolderAdmin($user, $organizationFolder),
			'DELETE' => $this->isOrganizationFolderAdmin($user, $organizationFolder),
			'UPDATE_MEMBERS' => $this->isOrganizationFolderAdmin($user, $organizationFolder),
			'MANAGE_ALL_RESOURCES' => $this->isOrganizationFolderAdmin($user, $organizationFolder),

			// At least Manager permissions required
			'READ_LIMITED' => $this->isOrganizationFolderManagerOrSubResourceManager($user, $organizationFolder), // FALSE if READ is allowed, as permission is implied
			'CREATE_TOP_LEVEL_RESOURCE' => $this->isOrganizationFolderAdminOrManager($user, $organizationFolder),
			'MANAGE_TOP_LEVEL_RESOURCES_WITH_INHERITANCE' => $this->isOrganizationFolderManager($user, $organizationFolder), // FALSE if MANAGE_ALL_RESOURCES is allowed, as permission is implied
			
			default => throw new \LogicException('This code should not be reached!')
		};
	}

	/**
	 * @param IUser $user
	 * @param OrganizationFolder $organizationFolder
	 * @return bool
	 */
	private function isOrganizationFolderMember(IUser $user, OrganizationFolder $organizationFolder): bool {
		$organizationFolderMembers = $this->organizationFolderMemberService->findAll($organizationFolder->getId());
		
		foreach($organizationFolderMembers as $organizationFolderMember) {
			if($this->userIsPrincipal($user, $organizationFolderMember->getPrincipal())) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param IUser $user
	 * @param OrganizationFolder $organizationFolder
	 * @return bool
	 */
	private function isOrganizationFolderAdmin(IUser $user, OrganizationFolder $organizationFolder): bool {
		$organizationFolderMembers = $this->organizationFolderMemberService->findAll($organizationFolder->getId(), [
			"permissionLevel" => OrganizationFolderMemberPermissionLevel::ADMIN,
		]);
		
		foreach($organizationFolderMembers as $organizationFolderMember) {
			// should be true for all returned members because of the filter, double check because of the big security implications
			if($organizationFolderMember->getPermissionLevel() === OrganizationFolderMemberPermissionLevel::ADMIN->value) {
				if($this->userIsPrincipal($user, $organizationFolderMember->getPrincipal())) {
					return true;
				}
			}
		}

		return $this->userIsNextcloudAdmin($user);
	}

	/**
	 * @param IUser $user
	 * @param OrganizationFolder $organizationFolder
	 * @return bool
	 */
	private function isOrganizationFolderManager(IUser $user, OrganizationFolder $organizationFolder): bool {
		$organizationFolderMembers = $this->organizationFolderMemberService->findAll($organizationFolder->getId(), [
			"permissionLevel" => OrganizationFolderMemberPermissionLevel::MANAGER,
		]);
		
		foreach($organizationFolderMembers as $organizationFolderMember) {
			// should be true for all returned members because of the filter, double check because of the big security implications
			if($organizationFolderMember->getPermissionLevel() === OrganizationFolderMemberPermissionLevel::MANAGER->value) {
				if($this->userIsPrincipal($user, $organizationFolderMember->getPrincipal())) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param IUser $user
	 * @param OrganizationFolder $organizationFolder
	 * @return bool
	 */
	private function isOrganizationFolderManagerOrSubResourceManager(IUser $user, OrganizationFolder $organizationFolder): bool {
		if($this->isOrganizationFolderManager($user, $organizationFolder)) {
			return true;
		}

		// TODO: potential performance improvement:
		// While we cannot fetch a whole subgraph of the resources graph without recursion (so this optimization is not possible if we are only checking a subgraph
		// like in the resourceVoter READ_LIMITED), we can fetch the whole resources graph of a groupfolder efficiently.
		// So instead of asking for READ_LIMITED on the top level resources we could fetch all resources of the organization folder here and check them here
		// as a flat list instead of recursively with fewer queries. We could also use a join to get all members directly with just one query.

		$resources = $this->resourceService->findAll($organizationFolder->getId());

		/**
		 * @var ResourceVoter
		 */
		$resourceVoter = \OC::$server->get(ResourceVoter::class);

		foreach ($resources as $resource) {
			if($resourceVoter->vote($user, $resource, ["READ_DIRECT", "READ_LIMITED"]) === self::ACCESS_GRANTED) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param IUser $user
	 * @param OrganizationFolder $organizationFolder
	 * @return bool
	 */
	private function isOrganizationFolderAdminOrManager(IUser $user, OrganizationFolder $organizationFolder): bool {
		$organizationFolderMembers = $this->organizationFolderMemberService->findAll($organizationFolder->getId());
		
		foreach($organizationFolderMembers as $organizationFolderMember) {
			$permissionLevel = $organizationFolderMember->getPermissionLevel();
			if($permissionLevel === OrganizationFolderMemberPermissionLevel::ADMIN->value || $permissionLevel === OrganizationFolderMemberPermissionLevel::MANAGER->value) {
				if($this->userIsPrincipal($user, $organizationFolderMember->getPrincipal())) {
					return true;
				}
			}
		}

		return $this->userIsNextcloudAdmin($user);
	}

	private function userIsInGroup(IUser $user, string $groupId): bool {
		return $this->groupManager->isInGroup($user->getUID(), $groupId);
	}

	private function userIsPrincipal(IUser $user, Principal $principal): bool {
		if($principal->isValid()) {
			if($principal instanceof PrincipalBackedByGroup) {
				return $this->userIsInGroup($user, $principal->getBackingGroupId());
			} else {
				// user principals are not supported by Organization Folder Members and
				// a principal object with that type should have never been put into this function
				return false;
			}
		} else {
			return false;
		}
	}

	private function userIsNextcloudAdmin(IUser $user): bool {
		return $this->groupManager->isAdmin($user->getUID());
	}
}
