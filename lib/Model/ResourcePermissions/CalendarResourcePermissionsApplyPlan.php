<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCP\IGroupManager;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;

use OCA\OrganizationFolders\Integration\Dav\CalendarIntegration;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\CalendarResource;
use OCA\OrganizationFolders\Model\CalendarSharesUpdatePlan;

class CalendarResourcePermissionsApplyPlan extends ResourcePermissionsApplyPlan {
	public function __construct(
		private readonly CalendarIntegration $calendarIntegration,
		private readonly IGroupManager $groupManager,
		private readonly CalendarResource $resource,
		private readonly CalendarSharesUpdatePlan $calendarSharesUpdatePlan,
	){}

	function getResource(): CalendarResource {
		return $this->resource;
	}

	/**
	 * @psalm-return list<array{principaluri: string, access: int}>
	 */
	function getAdditions(): array {
		return $this->calendarSharesUpdatePlan->toCreate;
	}

	/**
	 * @psalm-return list<array{id: int, principaluri: string, access: int}>
	 */
	function getUpdates(): array {
		return $this->calendarSharesUpdatePlan->toUpdate;
	}

	/**
	 * @psalm-return list<array{id: int, principaluri: string, access: int}>
	 */
	function getDeletions(): array {
		return $this->calendarSharesUpdatePlan->toRemove;
	}


	function getNumberOfAdditions(): int {
		return $this->calendarSharesUpdatePlan->getNumberOfAdditions();
	}

	function getNumberOfUpdates(): int {
		return $this->calendarSharesUpdatePlan->getNumberOfUpdates();
	}

	function getNumberOfDeletions(): int {
		return $this->calendarSharesUpdatePlan->getNumberOfDeletions();
	}

	function getNumberOfEffectivePermissionsAdditions(): int {
		return $this->calendarSharesUpdatePlan->getNumberOfAdditions();
	}

	function getNumberOfEffectivePermissionsUpdates(): int {
		return $this->calendarSharesUpdatePlan->getNumberOfUpdates();
	}

	function getNumberOfEffectivePermissionsDeletions(): int {
		return $this->calendarSharesUpdatePlan->getNumberOfDeletions();
	}

	private function getNumberOfUsersInShares(array $shares): int {
		$result = 0;

		foreach($shares as $share) {
			$result += $this->getNumberOfUsersInShare($share);
		}

		return $result;
	}

	/**
	 * @psalm-param array{principaluri: string, access: int}
	 */
	private function getNumberOfUsersInShare(array $share): int {
		[$davPrincipalType, $davPrincipalId] = \Sabre\Uri\split($share['principaluri']);

		if($davPrincipalType === "principals/users") {
			return 1;
		} else if ($davPrincipalType === "principals/groups") {
			return $this->groupManager->get($davPrincipalId)?->count() ?? 0;
		} else {
			return 0;
		}
	}

	function getNumberOfUsersWithPermissionsPotentiallyAdded(): int {
		return $this->getNumberOfUsersInShares($this->calendarSharesUpdatePlan->toCreate);
	}

	function getNumberOfUsersWithPermissionsPotentiallyChanged(): int {
		return $this->getNumberOfUsersInShares($this->calendarSharesUpdatePlan->toUpdate);
	}

	function getNumberOfUsersWithPermissionsPotentiallyDeleted(): int {
		return $this->getNumberOfUsersInShares($this->calendarSharesUpdatePlan->toRemove);
	}

	function apply(): void {
		$this->calendarIntegration->applySharesUpdatePlan($this->getResource()->getCalendarId(), $this->calendarSharesUpdatePlan);
	}
}