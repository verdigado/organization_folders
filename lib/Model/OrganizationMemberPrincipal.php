<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IL10N;
use OCP\IGroupManager;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProvider;
use OCA\OrganizationFolders\Enum\PrincipalType;

class OrganizationMemberPrincipal extends PrincipalBackedByGroup {
	private bool $valid;

	public function __construct(
		PrincipalFactory $factory,
		OrganizationProviderManager $organizationProviderManager,
		private readonly IL10N $l10n,
		IGroupManager $groupManager,
		private readonly string $providerId,
		private readonly int $organizationId,
		private ?OrganizationProvider $provider = null,
		private ?Organization $organization = null,
	) {
		parent::__construct($factory, $groupManager, $organizationProviderManager);

		if($provider === null || $organization === null) {
			try {
				$this->provider = $this->organizationProviderManager->getOrganizationProvider($providerId);
				$this->organization = $this->provider->getOrganization($organizationId);
				$this->valid = $this->organization !== null;
			} catch (\Exception $e) {
				$this->valid = false;
			}
		} else {
			$this->valid = true;
		}
	}

	public function getType(): PrincipalType {
		return PrincipalType::ORGANIZATION_MEMBER;
	}

	public function getId(): string {
		return $this->providerId . ":"  . $this->organizationId;
	}

	public function isValid(): bool {
		return $this->valid;
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
			$result = [$membersTranslation, $this->getFriendlyName()];

			try {
				$organization = $this->organization;

				while($organization?->getParentOrganizationId() && $organization = $this->provider->getOrganization($organization->getParentOrganizationId())) {
					$result[] = $organization->getFriendlyName();
				}

				$result[] = $this->provider->getFriendlyName();
			} catch (\Exception $e) {
				// fall back to without hierarchy
				return [$this->provider->getFriendlyName(), $this->getFriendlyName(), $membersTranslation];
			}

			return array_reverse($result);
		} else {
			return [$this->getFriendlyName(), $membersTranslation];
		}
	}

	public function getBackingGroupId(): ?string {
		return $this->getOrganization()?->getMembersGroup();
	}

	public function containsPrincipal(Principal $principal): bool {
		if($this->isValid() && $principal->isValid()) {
			if ($principal instanceof OrganizationMemberPrincipal && $this->getOrganizationProviderId() === $principal->getOrganizationProviderId()) {
				// if $this organization is a parent organization of given organization and
				// a chain of membership implying parent memberships exists return true

				try {
					$principalOrganization = $principal->getOrganization();

					do {
						if($principalOrganization->getId() === $this->organizationId) {
							return true;
						}
					}
					while(
						$principalOrganization?->getParentOrganizationId() &&
						$principalOrganization?->getMembershipImpliesParentMembership() &&
						$principalOrganization = $this->provider->getOrganization($principalOrganization->getParentOrganizationId())
					);
				} catch (\Exception $e) {
					return false;
				}
			}

			return parent::containsPrincipal($principal);
		}

		return false;
	}
}