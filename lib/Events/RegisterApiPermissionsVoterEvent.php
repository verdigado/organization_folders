<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCP\EventDispatcher\Event;

use OCA\OrganizationFolders\Registry\ApiPermissionsVoterRegistry;
use OCA\OrganizationFolders\ApiPermissionsVoter\ApiPermissionsVoter;

/**
 * Other apps can use this event to register an API Permissions Voter
 */
class RegisterApiPermissionsVoterEvent extends Event {

	public function __construct(private ApiPermissionsVoterRegistry $registry) {
		parent::__construct();
	}

	public function getRegistry(): ApiPermissionsVoterRegistry {
		return $this->registry;
	}

	public function registerProvider(ApiPermissionsVoter $voter, string $subjectType, int $priority): void {
		$this->registry->registerVoter($voter, $subjectType, $priority);
	}
}