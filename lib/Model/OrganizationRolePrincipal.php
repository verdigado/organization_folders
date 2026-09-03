<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IGroupManager;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Enum\PrincipalType;

class OrganizationRolePrincipal extends PrincipalBackedByGroup {
	private bool $valid;

	public function __construct(
		PrincipalFactory $factory,
		IGroupManager $groupManager,
		OrganizationProviderManager $organizationProviderManager,
		private readonly string $providerId,
		private readonly string $roleId,
		private ?OrganizationRole $role = null,
	) {
		parent::__construct($factory, $groupManager, $organizationProviderManager);

		if($role === null) {
			try {
				$this->role = $this->organizationProviderManager->getOrganizationProvider($providerId)->getRole($roleId);
				$this->valid = $this->role !== null;
			} catch (\Exception $e) {
				$this->valid = false;
			}
		} else {
			$this->valid = true;
		}
	}

	public function getType(): PrincipalType {
		return PrincipalType::ORGANIZATION_ROLE;
	}

	public function getId(): string {
		return $this->providerId . ":"  . $this->roleId;
	}

	public function isValid(): bool {
		return $this->valid;
	}

	public function getOrganizationProviderId(): string {
		return $this->providerId;
	}

	public function getRoleId(): string {
		return $this->roleId;
	}

	public function getRole(): ?OrganizationRole {
		return $this->role;
	}

	public function getFriendlyName(): string {
		return $this->role?->getFriendlyName() ?? $this->getId();
	}

	public function getFullHierarchyNames(): array {
		$result = [];

		$result[] = $this->getFriendlyName();

		if($this->valid) {
			$organizationProvider = $this->organizationProviderManager->getOrganizationProvider($this->providerId);
			
			try {
				$organization = $organizationProvider->getOrganization($this->role->getOrganizationId());
				$result[] = $organization->getFriendlyName();
	
				while($organization->getParentOrganizationId() && $organization = $organizationProvider->getOrganization($organization->getParentOrganizationId())) {
					$result[] = $organization->getFriendlyName();
				}

				$result[] = $organizationProvider->getFriendlyName();
			} catch (\Exception $e) {
				// fall back to without hierarchy
				$result = [$this->getFriendlyName()];
			}
		}

		return array_reverse($result);
	}

	public function getBackingGroupId(): ?string {
		return $this->getRole()?->getMembersGroup();
	}
}