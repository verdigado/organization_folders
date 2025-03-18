<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganizationProvider;

use OCP\EventDispatcher\IEventDispatcher;

use OCA\OrganizationFolders\Errors\OrganizationProviderNotFound;
use OCA\OrganizationFolders\Events\RegisterOrganizationProviderEvent;

class OrganizationProviderManager {
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
	public function getOrganizationProvider($id): OrganizationProvider {
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
}