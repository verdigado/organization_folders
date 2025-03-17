<?php

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class OrganizationMemberPrincipal extends Principal {
    private ?Organization $organization = null;

    public function __construct(
        private OrganizationProviderManager $organizationProviderManager,
        private string $providerId,
        private int $organizationId,
	) {
        try {
            $this->organization = $this->organizationProviderManager->getOrganizationProvider($providerId)->getOrganization($organizationId);
            $this->valid = !is_null($this->organization);
        } catch (\Exception $e) {
            $this->valid = false;
        }
    }

    public function getType(): PrincipalType {
		return PrincipalType::ORGANIZATION_MEMBER;
	}

    public function getId(): string {
		return $this->providerId . ":"  . $this->organizationId;
	}

    public function getOrganizationProviderId(): string {
        return $this->providerId;
    }

    public function getOrganizationId(): int {
        return $this->organizationId;
    }

    public function getOrganization(): ?Organization {
        return $this->organization;
    }

    public function getFriendlyName(): string {
        return $this->organization->getFriendlyName() ?? $this->getId();
    }

    public function getFullHierarchyNames(): array {
        $result = [];

        $result[] = "Members";

        $result[] = $this->getFriendlyName();

        if($this->valid) {
            $organizationProvider = $this->organizationProviderManager->getOrganizationProvider($this->providerId);

            while($organization->getParentOrganizationId() && $organization = $organizationProvider->getOrganization($organization->getParentOrganizationId())) {
                $result[] = $organization->getFriendlyName();
            }
        }

        return array_reverse($result);
    }
}