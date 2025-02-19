<?php

namespace OCA\OrganizationFolders\Security;

use OCP\IUser;
use OCP\IGroupManager;

use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class OrganizationFolderVoter extends Voter {
	public function __construct(
        private OrganizationFolderMemberService $organizationFolderMemberService,
		private IGroupManager $groupManager,
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
            'READ_LIMITED' => $this->isOrganizationFolderManager($user, $organizationFolder), // FALSE if READ is allowed, as permission is implied
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

    private function userHasRole(IUser $user, string $organizationProviderId, string $roleId): bool {
        $organizationProvider = $this->organizationProviderManager->getOrganizationProvider($organizationProviderId);
        $role = $organizationProvider->getRole($roleId);

        return $this->userIsInGroup($user, $role->getMembersGroup());
    }

    private function userIsPrincipal(IUser $user, Principal $principal): bool {
        if($principal->getType() === PrincipalType::GROUP) {
            return $this->userIsInGroup($user, $principal->getId());
        } else if($principal->getType() === PrincipalType::ROLE) {
            [$organizationProviderId, $roleId] = explode(":", $principal->getId(), 2);
            
            return $this->userHasRole($user, $organizationProviderId, $roleId);
        } else {
            // user principals are not supported by Organization Folder Members and
            // a principal object with that type should have never been put into this function
            return false;
        }
    }

    private function userIsNextcloudAdmin(IUser $user): bool {
        return $this->groupManager->isAdmin($user->getUID());
    }
}
