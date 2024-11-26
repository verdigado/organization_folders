<?php

namespace OCA\OrganizationFolders\Model;

use OCP\IUserManager;
use OCP\IGroupManager;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class PrincipalFactory {
    public function __construct(
        protected IUserManager $userManager,
        protected IGroupManager $groupManager,
        protected OrganizationProviderManager $organizationProviderManager,
	) {
    }

    public function buildPrincipal(PrincipalType $type, string $id): Principal {
        if($type === PrincipalType::USER) {
            return new UserPrincipal($this->userManager, $id);
        } else if ($type === PrincipalType::GROUP) {
            return new GroupPrincipal($this->groupManager, $id);
        } else if ($type === PrincipalType::ROLE) {
            [$organizationProviderId, $roleId] = explode(":", $id, 2);
            if(!(isset($organizationProviderId) && isset($roleId))) {
                throw new \Exception("Invalid id format for principal of type role");
            }
            return new RolePrincipal($this->organizationProviderManager, $organizationProviderId, $roleId);
        } else {
            throw new \Exception("invalid PrincipalType");
        }
    }
}