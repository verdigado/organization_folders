<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganisationProvider;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\Server;

use OCA\OrganizationFolders\Events\RegisterOrganisationProviderEvent;

class OrganisationProviderManager {
	private array $organisationProviders = [];

	public function __construct(
		IEventDispatcher $dispatcher,
	) {
		$event = new RegisterOrganisationProviderEvent($this);
		$dispatcher->dispatchTyped($event);
	}

	/**
	 * @return OrganisationProvider[]
	 */
	public function getOrganisationProviders(): array {
		return $this->organisationProviders;
	}

	/**
	 * @return OrganisationProvider
	 */
	public function getOrganisationProvider($id): ?OrganisationProvider {
		return $this->organisationProviders[$id];
	}

	public function registerOrganisationProvider(OrganisationProvider $organisationProvider): self {
		$this->organisationProviders[$organisationProvider->getId()] = $organisationProvider;
		return $this;
	}
}