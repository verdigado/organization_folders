<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCP\EventDispatcher\Event;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

/**
 * This event is triggered during the initialization of Organization Folders.
 */
class RegisterOrganizationProviderEvent extends Event {

	/** @var OrganizationProviderManager */
	private $organizationProviderManager;

	public function __construct(OrganizationProviderManager $organizationProviderManager) {
		parent::__construct();
		$this->organizationProviderManager = $organizationProviderManager;
	}

	public function getOrganizationProviderManager(): OrganizationProviderManager {
		return $this->organizationProviderManager;
	}
}