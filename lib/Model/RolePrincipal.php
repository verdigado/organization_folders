<?php

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class RolePrincipal extends Principal {
    private ?OrganizationRole $role;

    public function __construct(
        private OrganizationProviderManager $organizationProviderManager,
        private string $providerId,
        private string $roleId,
	) {
        try {
            $this->role = $this->organizationProviderManager->getOrganizationProvider($providerId)->getRole($roleId);
            $this->valid = !is_null($this->role);
        } catch (\Exception $e) {
            $this->valid = false;
        }
    }

    public function getType(): PrincipalType {
		return PrincipalType::ROLE;
	}

    public function getId(): string {
		return $this->providerId . ":"  . $this->roleId;
	}

    public function getFriendlyName(): string {
        return $this->role->getFriendlyName();
    }

    public function getFullHierarchyNames(): array {
        $result = [];
        $result[] = $this->getFriendlyName();

        $organizationProvider = $this->organizationProviderManager->getOrganizationProvider($this->providerId);
        $organization = $organizationProvider->getOrganization($this->role->getOrganizationId());
        $result[] = $organization->getFriendlyName();

        while($organization->getParentOrganizationId() && $organization = $organizationProvider->getOrganization($organization->getParentOrganizationId())) {
            $result[] = $organization->getFriendlyName();
        }

        return array_reverse($result);
    }
}