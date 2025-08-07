<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IUser;
use OCP\IGroup;
use OCP\IGroupManager;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;

/* Principal, that is backed by/can be resolved to a Nextcloud Group. */
abstract class PrincipalBackedByGroup extends Principal {
	public function __construct(
		protected readonly IGroupManager $groupManager,
	) {}

	/**
	 * Get the id of the nextcloud group that backs this principal
	 * Can return null only if principal is not valid (->isValid() == false)
	 * @return string|null
	 */
	abstract public function getBackingGroupId(): ?string;

	/**
	 * Get the nextcloud group that backs this principal
	 * @return IGroup|null
	 */
	public function getBackingGroup(): ?IGroup {
		try {
			if($this->isValid()) {
				return $this->groupManager->get($this->getBackingGroupId());
			} else {
				return null;
			}
		} catch (\Exception $e) {
			return null;
		}
	}

	public function getNumberOfUsersContained(): int {
		if($this->valid) {
			return $this->getBackingGroup()?->count() ?? 0;
		} else {
			return 0;
		}
	}

	/**
	 * @return IUser[]
	 */
	public function getUsersContained(): array {
		if($this->valid) {
			return $this->getBackingGroup()?->getUsers() ?? [];
		} else {
			return [];
		}
	}

	public function toGroupfolderAclMapping(): ?IUserMapping {
		$group = $this->getBackingGroupId();

		if(isset($group) && $group != '') {
			return new UserMapping(type: "group", id: $group, displayName: null);
		} else {
			return null;
		}
	}

	public function isEquivalent(Principal $principal): bool {
		if($this->isValid() && $principal->isValid()) {
			if($principal instanceof PrincipalBackedByGroup) {
				return $principal->getBackingGroupId() === $this->getBackingGroupId();
			}
		}

		return false;
	}

	/**
	 * @param IUser[] $set
	 * @param IUser[] $potentialSubset
	 * @return bool
	 */
	private function isSubsetOfUsers(array $set, array $potentialSubset) {
		$uidMap = [];

		foreach($set as $user) {
			$uidMap[$user->getUID()] = true;
		}

		foreach($potentialSubset as $user) {
			if(!isset($uidMap[$user->getUID()])) {
				return false;
			}
		}

		return true;
	}

	public function containsPrincipal(Principal $principal, bool $skipExpensiveOperations = false): bool {
		if($this->isValid() && $principal->isValid()) {
			if($principal instanceof UserPrincipal) {
				return $this->groupManager->isInGroup($principal->getId(), $this->getBackingGroupId());
			} else if ($principal instanceof PrincipalBackedByGroup) {
				if($principal->getBackingGroupId() === $this->getBackingGroupId()) {
					return true;
				}

				if(!$skipExpensiveOperations && $this->getBackingGroup()->count() >= $principal->getBackingGroup()->count()) {
					// TODO: find way to get array of userIds instead of IUser objects to improve performance
					return $this->isSubsetOfUsers($this->getBackingGroup()->getUsers(), $principal->getBackingGroup()->getUsers());
				}
			}
		}

		return false;
	}
}