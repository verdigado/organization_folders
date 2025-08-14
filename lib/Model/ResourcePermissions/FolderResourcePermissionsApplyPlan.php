<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCP\IGroupManager;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;

use OCA\OrganizationFolders\Manager\ACLManager;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Model\GroupfolderACLsUpdatePlan;

class FolderResourcePermissionsApplyPlan extends ResourcePermissionsApplyPlan {
	public function __construct(
		protected readonly ACLManager $aclManager,
		protected readonly IGroupManager $groupManager,
		private readonly FolderResource $resource,
		private readonly GroupfolderACLsUpdatePlan $groupfolderACLsUpdatePlan,
	){}

	function getResource(): Resource {
		return $this->resource;
	}

	function getNumberOfEffectivePermissionsAdditions(): int {
		return count($this->groupfolderACLsUpdatePlan->toCreate);
	}

	function getNumberOfEffectivePermissionsUpdates(): int {
		return count($this->groupfolderACLsUpdatePlan->toUpdate);
	}

	function getNumberOfEffectivePermissionsDeletions(): int {
		return count($this->groupfolderACLsUpdatePlan->toRemove);
	}

	private function getNumberOfUsersInACLs(array $acls): int {
		$result = 0;

		foreach($acls as $acl) {
			$result += $this->getNumberOfUsersInUserMapping($acl->getUserMapping());
		}

		return $result;
	}

	private function getNumberOfUsersInUserMapping(IUserMapping $userMapping): int {
		if($userMapping->getType() === "user") {
			return 1;
		} else if ($userMapping->getType() === "group") {
			return $this->groupManager->get($userMapping->getId())?->count() ?? 0;
		} else {
			return 0;
		}
	}

	function getNumberOfUsersWithPermissionsPotentiallyAdded(): int {
		return $this->getNumberOfUsersInACLs($this->groupfolderACLsUpdatePlan->toCreate);
	}

	function getNumberOfUsersWithPermissionsPotentiallyChanged(): int {
		return $this->getNumberOfUsersInACLs($this->groupfolderACLsUpdatePlan->toUpdate);
	}

	function getNumberOfUsersWithPermissionsPotentiallyDeleted(): int {
		$result = $this->getNumberOfUsersInACLs($this->groupfolderACLsUpdatePlan->toRemove);

		// 0 Permissions are essentially deletions
		// TODO: Once 0 permissions are no longer created anywhere in the codebase, this can be deleted
		foreach($this->groupfolderACLsUpdatePlan->toUpdate as $acl) {
			if($acl->getPermissions() === 0) {
				$result += $this->getNumberOfUsersInUserMapping($acl->getUserMapping());
			}
		}

		return $result;
	}

	function apply(): void {
		$this->aclManager->applyUpdatePlan($this->groupfolderACLsUpdatePlan);
	}
}