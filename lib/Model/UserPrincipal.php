<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroupManager;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class UserPrincipal extends Principal {
	private bool $valid;

	private bool $initialized = false;

	public function __construct(
		private readonly PrincipalFactory $principalFactory,
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
		private readonly OrganizationProviderManager $organizationProviderManager,
		private readonly string $id,
		bool $lazy = true,
		private ?IUser $user = null,
	) {
		if($this->user === null) {
			// IUser not provided to constructor, looking it up by id
			if(!$lazy) {
				$this->init();
			}
		} else {
			// IUser provided to constructor
			$this->valid = true;
			$this->initialized = true;
		}
	}

	private function init(): void {
		try {
			$this->user = $this->userManager->get($this->id);
			$this->valid = $this->user !== null;
		} catch (\Exception $e) {
			$this->valid = false;
		}
		$this->initialized = true;
	}

	public function getType(): PrincipalType {
		return PrincipalType::USER;
	}

	public function getId(): string {
		return $this->id;
	}

	public function isValid(): bool {
		if(!$this->initialized) {
			$this->init();
		}

		return $this->valid;
	}

	public function getFriendlyName(): string {
		if(!$this->initialized) {
			$this->init();
		}

		return $this->user?->getDisplayName() ?? $this->getId();
	}

	public function getNumberOfUsersContained(): int {
		if(!$this->initialized) {
			$this->init();
		}

		if($this->valid) {
			return 1;
		} else {
			return 0;
		}
	}

	/**
	 * @return IUser[]
	 */
	public function getUsersContained(): array {
		if(!$this->initialized) {
			$this->init();
		}

		if($this->valid) {
			return [$this->user];
		} else {
			return [];
		}
	}

	public function toGroupfolderAclMapping(): ?IUserMapping {
		if($this->id != '') {
			return new UserMapping(type: "user", id: $this->id, displayName: null);
		} else {
			return null;
		}
	}

	public function toDavPrincipalURI(): string {
		return "principals/users/" . $this->id;
	}

	public function isEquivalent(Principal $principal): bool {
		if(!$this->initialized) {
			$this->init();
		}

		if($this->isValid() && $principal->isValid()) {
			if($principal instanceof UserPrincipal) {
				return $principal->getId() === $this->getId();
			}
		}

		return false;
	}

	public function containsPrincipal(Principal $principal): bool {
		return $this->isEquivalent($principal);
	}

	public function getPrincipalsIsContainedIn(): array {
		if(!$this->initialized) {
			$this->init();
		}

		// contained by itself
		$result = [$this];

		if(!$this->valid) {
			return $result;
		}

		// TODO: This also asks our own virtual group provider, which we know we can ignore
		$groups = $this->groupManager->getUserGroups($this->user);

		foreach($groups as $group) {
			// GroupPrincipals
			$result[] = $this->principalFactory->buildFromIGroup($group);

			// OrganizationMemberPrincipals
			// Recursion is not needed, as user group memberships already resolve impliedParentMemberships
			foreach($this->organizationProviderManager->getOrganizationsByMembersGroupId($group->getGID()) as $organization) {
				$result[] = $this->principalFactory->buildFromOrganization($organization);
			}

			// OrganizationRolePrincipals
			foreach($this->organizationProviderManager->getRolesByMembersGroupId($group->getGID()) as $role) {
				$result[] = $this->principalFactory->buildFromOrganizationRole($role);
			}
		}

		return $result;
	}
}