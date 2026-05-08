<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

class CalendarSharesUpdatePlan {

	/**
	 * @param list<array{principaluri: string, access: int}> $toCreate
	 * @param list<array{id: int, principaluri: string, access: int}> $toUpdate
	 * @param list<array{id: int, principaluri: string, access: int}> $toRemove
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