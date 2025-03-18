<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

/* Principal, that is backed by/can be resolved to a Nextcloud Group. */
abstract class PrincipalBackedByGroup extends Principal {
	/**
	 * Get the nextcloud group that backs this principal
	 * Can return null only if principal is not valid (->isValid() == false)
	 * @return string|null
	 */
	abstract public function getBackingGroup(): ?string;
}