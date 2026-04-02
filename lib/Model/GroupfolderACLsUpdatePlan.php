<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\GroupFolders\ACL\Rule;

class GroupfolderACLsUpdatePlan {

	/**
	 * @param Rule[] $toCreate
	 * @param Rule[] $toUpdate
	 * @param Rule[] $toRemove
	 */
	public function __construct(
		public readonly array $toCreate,
		public readonly array $toUpdate,
		public readonly array $toRemove,
	) {}

	public function getNumberOfAdditions(): int {
		return count($this->toCreate);
	}

	public function getNumberOfUpdates(): int {
		return count($this->toUpdate);
	}

	public function getNumberOfDeletions(): int {
		return count($this->toRemove);
	}
}