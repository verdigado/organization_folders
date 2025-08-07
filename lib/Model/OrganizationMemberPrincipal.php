<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IL10N;
use OCP\IGroupManager;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Enum\PrincipalType;

class OrganizationMemberPrincipal extends PrincipalBackedByGroup {
	private ?Organization $organization = null;

	public function __construct(
		private readonly OrganizationProviderManager $organizationProviderManager,
		private readonly IL10N $l10n,
		IGroupManager $groupManager,
		private string $providerId,
		private int $organizationId,
	) {
		parent::__construct($groupManager);

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
		return $this->organization?->getFriendlyName() ?? $this->getId();
	}

	public function getFullHierarchyNames(): array {
		$membersTranslation = $this->l10n->t('Members');

		if($this->valid) {
			$organizationProvider = $this->organizationProviderManager->getOrganizationProvider($this->providerId);

			$result = [$membersTranslation, $this->getFriendlyName()];

			try {
				$organization = $this->organization;

				while($organization?->getParentOrganizationId() && $organization = $organizationProvider->getOrganization($organization->getParentOrganizationId())) {
					$result[] = $organization->getFriendlyName();
				}

				$result[] = $organizationProvider->getFriendlyName();
			} catch (\Exception $e) {
				// fall back to without hierarchy
				return [$organizationProvider->getFriendlyName(), $this->getFriendlyName(), $membersTranslation];
			}

			return array_reverse($result);
		} else {
			return [$this->getFriendlyName(), $membersTranslation];
		}
	}

	public function getBackingGroupId(): ?string {
		return $this->getOrganization()?->getMembersGroup();
	}

	public function containsPrincipal(Principal $principal, bool $skipExpensiveOperations = false): bool {
		if($this->isValid() && $principal->isValid()) {
			// Fast path with assurances made by OrganizationProvider
			if ($principal instanceof OrganizationMemberPrincipal && $this->getOrganizationProviderId() === $principal->getOrganizationProviderId()) {
				$organizationProvider = $this->organizationProviderManager->getOrganizationProvider($this->providerId);

				$principalOrganization = $principal->getOrganization();

				do {
					if($principalOrganization->getId() === $this->organizationId) {
						return true;
					}
				}
				while(
					$principalOrganization?->getParentOrganizationId() &&
					$principalOrganization?->getMembershipImpliesParentMembership() &&
					$principalOrganization = $organizationProvider->getOrganization($principalOrganization->getParentOrganizationId())
				);
			}

			// Slow path with manual user subset checking
			return parent::containsPrincipal($principal, $skipExpensiveOperations);
		}

		return false;
	}
}