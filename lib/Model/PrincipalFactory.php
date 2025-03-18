<?php

declare(strict_types=1);

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
		} else if ($type === PrincipalType::ORGANIZATION_MEMBER) {
			[$organizationProviderId, $organizationId] = explode(":", $id, 2);

			if(!(isset($organizationProviderId) && isset($organizationId) && ctype_digit($organizationId))) {
				throw new \Exception("Invalid id format for principal of type organization member");
			}

			return new OrganizationMemberPrincipal($this->organizationProviderManager, $organizationProviderId, (int)$organizationId);
		} else if ($type === PrincipalType::ORGANIZATION_ROLE) {
			[$organizationProviderId, $roleId] = explode(":", $id, 2);

			if(!(isset($organizationProviderId) && isset($roleId))) {
				throw new \Exception("Invalid id format for principal of type organization role");
			}

			return new OrganizationRolePrincipal($this->organizationProviderManager, $organizationProviderId, $roleId);
		} else {
			throw new \Exception("invalid PrincipalType");
		}
	}
}