<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCP\EventDispatcher\Event;

use OCA\OrganizationFolders\Transport\OrganisationProviderManager;

/**
 * This event is triggered during the initialization of Organisation Folders.
 */
class RegisterOrganisationProviderEvent extends Event {

	/** @var OrganisationProviderManager */
	private $organisationProviderManager;

	public function __construct(OrganisationProviderManager $organisationProviderManager) {
		parent::__construct();
		$this->organisationProviderManager = $organisationProviderManager;
	}

	public function getOrganisationProviderManager(): OrganisationProviderManager {
		return $this->organisationProviderManager;
	}
}