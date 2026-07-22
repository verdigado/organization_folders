<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganizationProvider;

use OCP\EventDispatcher\IEventDispatcher;

use OCA\OrganizationFolders\Errors\Api\OrganizationProviderNotFound;
use OCA\OrganizationFolders\Events\RegisterOrganizationProviderEvent;
use OCA\OrganizationFolders\Model\Organization;
use OCA\OrganizationFolders\Model\OrganizationRole;

class OrganizationProviderManager {
	/** @var OrganizationProvider[] */
	private array $organizationProviders = [];

	public function __construct(
		IEventDispatcher $dispatcher,
	) {
		$event = new RegisterOrganizationProviderEvent($this);
		$dispatcher->dispatchTyped($event);
	}

	/**
	 * @return OrganizationProvider[]
	 */
	public function getOrganizationProviders(): array {
		return $this->organizationProviders;
	}

	/**
	 * @return bool
	 */
	public function hasOrganizationProvider($id): bool {
		return array_key_exists($id, $this->organizationProviders);
	}

	/**
	 * @return OrganizationProvider
	 * @throws OrganizationProviderNotFound
	 */
	public function getOrganizationProvider(string $id): OrganizationProvider {
		$organizationProvider = $this->organizationProviders[$id];
		
		if(isset($organizationProvider)) {
			return $organizationProvider;
		} else {
			throw new OrganizationProviderNotFound($id);
		}
	}

	public function registerOrganizationProvider(OrganizationProvider $organizationProvider): self {
		$this->organizationProviders[$organizationProvider->getId()] = $organizationProvider;
		return $this;
	}

	/**
	 * @param string $groupId
	 * @return Organization[]
	 */
	public function getOrganizationsByMembersGroupId(string $groupId): array {
		$result = [];

		foreach($this->organizationProviders as $organizationProvider) {
			foreach($organizationProvider->getOrganizationsByMembersGroupId($groupId) as $organization) {
				$result[] = $organization;
			}
		}

		return $result;
	}

	/**
	 * @param string $groupId
	 * @return OrganizationRole[]
	 */
	public function getRolesByMembersGroupId(string $groupId): array {
		$result = [];

		foreach($this->organizationProviders as $organizationProvider) {
			foreach($organizationProvider->getRolesByMembersGroupId($groupId) as $role) {
				$result[] = $role;
			}
		}

		return $result;
	}
}