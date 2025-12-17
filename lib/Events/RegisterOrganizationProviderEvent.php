<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCP\EventDispatcher\Event;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProvider;

/**
 * Other apps can use this event to register an OrganizationProvider
 */
class RegisterOrganizationProviderEvent extends Event {

	public function __construct(private OrganizationProviderManager $organizationProviderManager) {
		parent::__construct();
	}

	public function getOrganizationProviderManager(): OrganizationProviderManager {
		return $this->organizationProviderManager;
	}

	public function registerProvider(OrganizationProvider $organizationProvider): void {
		$this->organizationProviderManager->registerOrganizationProvider($organizationProvider);
	}
}