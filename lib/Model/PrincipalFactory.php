<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IL10N;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class PrincipalFactory {
	public function __construct(
		protected readonly IUserManager $userManager,
		protected readonly IGroupManager $groupManager,
		protected readonly IL10N $l10n,
		protected readonly OrganizationProviderManager $organizationProviderManager,
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

			return $this->buildOrganizationMemberPrincipal($organizationProviderId, (int)$organizationId);
		} else if ($type === PrincipalType::ORGANIZATION_ROLE) {
			[$organizationProviderId, $roleId] = explode(":", $id, 2);

			if(!(isset($organizationProviderId) && isset($roleId))) {
				throw new \Exception("Invalid id format for principal of type organization role");
			}

			return $this->buildOrganizationRolePrincipal($organizationProviderId, $roleId);
		} else {
			throw new \Exception("invalid PrincipalType");
		}
	}

	public function buildOrganizationMemberPrincipal(string $organizationProviderId, int $organizationId): OrganizationMemberPrincipal {
		return new OrganizationMemberPrincipal($this->organizationProviderManager, $this->l10n, $this->groupManager, $organizationProviderId, $organizationId);
	}

	public function buildOrganizationRolePrincipal(string $organizationProviderId, string $roleId): OrganizationRolePrincipal {
		return new OrganizationRolePrincipal($this->organizationProviderManager, $this->groupManager, $organizationProviderId, $roleId);
	}
}