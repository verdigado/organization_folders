<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;


use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCP\IUser;
use OCP\IGroup;
use OCP\IGroupManager;

/** Principal, that is backed by/can be resolved to a Nextcloud Group. */
abstract class PrincipalBackedByGroup extends Principal {
	public function __construct(
		PrincipalFactory $factory,
		protected readonly IGroupManager $groupManager,
		protected readonly OrganizationProviderManager $organizationProviderManager,
	) {
		parent::__construct($factory);
	}

	/**
	 * Get the id of the nextcloud group that backs this principal
	 * Can return null only if principal is not valid (->isValid() == false)
	 * @return string|null
	 */
	abstract public function getBackingGroupId(): ?string;

	/**
	 * Get the nextcloud group that backs this principal
	 * @return IGroup|null
	 */
	public function getBackingGroup(): ?IGroup {
		try {
			if($this->isValid()) {
				return $this->groupManager->get($this->getBackingGroupId());
			} else {
				return null;
			}
		} catch (\Exception $e) {
			return null;
		}
	}

	public function getNumberOfUsersContained(): int {
		if($this->isValid()) {
			return $this->getBackingGroup()?->count() ?? 0;
		} else {
			return 0;
		}
	}

	/**
	 * @return IUser[]
	 */
	public function getUsersContained(): array {
		if($this->isValid()) {
			return $this->getBackingGroup()?->getUsers() ?? [];
		} else {
			return [];
		}
	}

	public function toGroupfolderAclMapping(): ?IUserMapping {
		$group = $this->getBackingGroupId();

		if(isset($group) && $group != '') {
			return new UserMapping(type: "group", id: $group, displayName: null);
		} else {
			return null;
		}
	}

	public function toDavPrincipalURI(): string {
		return "principals/groups/" . $this->getBackingGroupId();
	}

	public function isEquivalent(Principal $principal): bool {
		if($this->isValid() && $principal->isValid()) {
			if($principal instanceof PrincipalBackedByGroup) {
				return $principal->getBackingGroupId() === $this->getBackingGroupId();
			}
		}

		return false;
	}

	public function containsPrincipal(Principal $principal): bool {
		if($this->isValid() && $principal->isValid()) {
			if($principal instanceof UserPrincipal) {
				return $this->groupManager->isInGroup($principal->getId(), $this->getBackingGroupId());
			} else if ($principal instanceof PrincipalBackedByGroup) {
				if($principal->getBackingGroupId() === $this->getBackingGroupId()) {
					return true;
				}
			}
		}

		return false;
	}

	public function getPrincipalsIsContainedIn(): array {
		return array_values($this->getPrincipalsIsContainedInRecursion($this->getBackingGroupId()));
	}

	private function getPrincipalsIsContainedInRecursion(string $gid): array {
		$result = [];

		// PrincipalType::GROUP
		$principal = $this->factory->buildPrincipal(PrincipalType::GROUP, $gid);
		$result[$principal->getKey()] = $principal;

		// PrincipalType::ORGANIZATION_ROLE
		foreach($this->organizationProviderManager->getRolesByMembersGroupId($gid) as $role) {
			$principal = $this->factory->buildFromOrganizationRole($role);
			$result[$principal->getKey()] = $principal;
		}

		// PrincipalType::ORGANIZATION_MEMBER
		foreach($this->organizationProviderManager->getOrganizationsByMembersGroupId($gid) as $organization) {
			$principal = $this->factory->buildFromOrganization($organization);
			$result[$principal->getKey()] = $principal;

			if($organization?->getParentOrganizationId() && $organization->getMembershipImpliesParentMembership()) {
				try {
					$parentOrganization = $this->organizationProviderManager->getOrganizationProvider($organization->getProviderId())->getOrganization($organization?->getParentOrganizationId());
					$result += $this->getPrincipalsIsContainedInRecursion($parentOrganization->getMembersGroup());
				} catch (\Exception $e) {}
			}
		}

		return $result;
	}
}