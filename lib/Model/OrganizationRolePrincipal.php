<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IGroupManager;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Enum\PrincipalType;

class OrganizationRolePrincipal extends PrincipalBackedByGroup {
	private ?OrganizationRole $role = null;

	public function __construct(
		private OrganizationProviderManager $organizationProviderManager,
		IGroupManager $groupManager,
		private string $providerId,
		private string $roleId,
	) {
		parent::__construct($groupManager);

		try {
			$this->role = $this->organizationProviderManager->getOrganizationProvider($providerId)->getRole($roleId);
			$this->valid = !is_null($this->role);
		} catch (\Exception $e) {
			$this->valid = false;
		}
	}

	public function getType(): PrincipalType {
		return PrincipalType::ORGANIZATION_ROLE;
	}

	public function getId(): string {
		return $this->providerId . ":"  . $this->roleId;
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