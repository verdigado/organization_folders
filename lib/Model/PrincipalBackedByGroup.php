<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

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

	public function getNumberOfAccountsContained(): int {
		if($this->valid) {
			return $this->getBackingGroup()?->count() ?? 0;
		} else {
			return 0;
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
}