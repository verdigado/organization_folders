<?php

namespace OCA\OrganizationFolders\Security;

use OCP\IUser;
use OCP\IGroupManager;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class ResourceVoter extends Voter {
	public function __construct(
		private ResourceService $resourceService,
        private ResourceMemberService $resourceMemberService,
		private IGroupManager $groupManager,
        private OrganizationProviderManager $organizationProviderManager,
	) {
	}
	protected function supports(string $attribute, mixed $subject): bool {
		return $subject instanceof Resource || $subject === Resource::class;
	}


	protected function voteOnAttribute(string $attribute, mixed $subject, ?IUser $user): bool {
		// _dlog($attribute, $subject);

		if (!$user) {
			return false;
        }

		/** @var Resource */
		$resource = $subject;
		return match ($attribute) {
			'READ' => $this->isGranted($user, $resource),
            'UPDATE' => $this->isGranted($user, $resource),
			'DELETE' => $this->isGranted($user, $resource),
            'UPDATE_MEMBERS' => $this->isGranted($user, $resource),
            'CREATE_SUBRESOURCE' => $this->isGranted($user, $resource),
			default => throw new \LogicException('This code should not be reached!')
		};
	}

    private function isResourceOrganizationFolderAdmin(IUser $user, Resource $resource): bool {
        // TODO: implement
        return false;
    }

	/**
	 * @param IUser $user
	 * @param Resource $resource
	 * @return bool
	 */
	private function isResourceManager(IUser $user, Resource $resource): bool {
        // TODO: check if is top-level resource and user is organizationFolder manager

		$resourceMembers = $this->resourceMemberService->findAll($resource->getId());

        foreach($resourceMembers as $resourceMember) {
            if($resourceMember->getPermissionLevel() === ResourceMemberPermissionLevel::MANAGER->value) {
                $principal = $resourceMember->getPrincipal();

                if($principal->getType() === PrincipalType::USER) {
                    if($principal->getId() === $user->getUID()) {
                        return true;
                    }
                } else if($principal->getType() === PrincipalType::GROUP) {
                    if($this->userIsInGroup($user, $principal->getId())) {
                        return true;
                    }
                } else if($principal->getType() === PrincipalType::ROLE) {
                    [$organizationProviderId, $roleId] = explode(":", $principal->getId(), 2);
					
                    if($this->userHasRole($user, $organizationProviderId, $roleId)) {
                        return true;
                    }
                }
            }
        }

        if($resource->getInheritManagers()) {
            $parentResource = $this->resourceService->getParentResource($resource);

            if(!is_null($parentResource)) {
                return $this->isResourceManager($user, $parentResource);
            }
        }

        return false;
	}

	protected function isGranted(IUser $user, Resource $resource): bool {
		return $this->isResourceOrganizationFolderAdmin($user, $resource) || $this->isResourceManager($user, $resource);
	}

    private function userIsInGroup(IUser $user, string $groupId): bool {
        return $this->groupManager->isInGroup($user->getUID(), $groupId);
    }

    private function userHasRole(IUser $user, string $organizationProviderId, string $roleId): bool {
        $organizationProvider = $this->organizationProviderManager->getOrganizationProvider($organizationProviderId);
        $role = $organizationProvider->getRole($roleId);

        return $this->userIsInGroup($user, $role->getMembersGroup());
	}
}