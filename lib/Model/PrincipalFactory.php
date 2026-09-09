<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IGroup;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class PrincipalFactory {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
		private readonly IL10N $l10n,
		private readonly OrganizationProviderManager $organizationProviderManager,
	) {
	}

	public function buildPrincipal(PrincipalType $type, string $id): Principal {
		if($type === PrincipalType::USER) {
			return new UserPrincipal($this, $this->userManager, $this->groupManager, $this->organizationProviderManager, $id);
		} else if ($type === PrincipalType::GROUP) {
			return new GroupPrincipal($this, $this->groupManager, $this->organizationProviderManager, $id);
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
			throw new \Exception("Invalid PrincipalType");
		}
	}

	public function buildOrganizationMemberPrincipal(string $organizationProviderId, int $organizationId): OrganizationMemberPrincipal {
		return new OrganizationMemberPrincipal($this, $this->organizationProviderManager, $this->l10n, $this->groupManager, $organizationProviderId, $organizationId);
	}

	public function buildOrganizationRolePrincipal(string $organizationProviderId, string $roleId): OrganizationRolePrincipal {
		return new OrganizationRolePrincipal($this, $this->groupManager, $this->organizationProviderManager, $organizationProviderId, $roleId);
	}

	public function buildFromIUser(IUser $user): UserPrincipal {
		return new UserPrincipal($this, $this->userManager, $this->groupManager, $this->organizationProviderManager, $user->getUID(), false, $user);
	}

	public function buildFromIGroup(IGroup $group): GroupPrincipal {
		return new GroupPrincipal($this, $this->groupManager, $this->organizationProviderManager, $group->getGID(), false, $group);
	}

	public function buildFromOrganization(Organization $organization): OrganizationMemberPrincipal {
		return new OrganizationMemberPrincipal(
			$this,
			$this->organizationProviderManager,
			$this->l10n,
			$this->groupManager,
			$organization->getProviderId(),
			$organization->getId(),
			$this->organizationProviderManager->getOrganizationProvider($organization->getProviderId()) ?? null,
			$organization
		);
	}

	public function buildFromOrganizationRole(OrganizationRole $role): OrganizationRolePrincipal {
		return new OrganizationRolePrincipal($this, $this->groupManager, $this->organizationProviderManager, $role->getProviderId(), $role->getId(), $role);
	}
}