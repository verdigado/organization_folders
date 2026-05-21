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
		private readonly ACLManager $aclManager,
		private readonly IGroupManager $groupManager,
		private readonly FolderResource $resource,
		private readonly GroupfolderACLsUpdatePlan $groupfolderACLsUpdatePlan,
	){}

	function getResource(): FolderResource {
		return $this->resource;
	}

	/**
	 * @psalm-return list<non-empty-array<string, mixed>>
	 */
	function getAdditions(): array {
		return array_map(fn($rule) => $rule->jsonSerialize(), $this->groupfolderACLsUpdatePlan->toCreate);
	}

	/**
	 * @psalm-return list<non-empty-array<string, mixed>>
	 */
	function getUpdates(): array {
		return array_map(fn($rule) => $rule->jsonSerialize(), $this->groupfolderACLsUpdatePlan->toUpdate);
	}

	/**
	 * @psalm-return list<non-empty-array<string, mixed>>
	 */
	function getDeletions(): array {
		return array_map(fn($rule) => $rule->jsonSerialize(), $this->groupfolderACLsUpdatePlan->toRemove);
	}


	function getNumberOfAdditions(): int {
		return $this->groupfolderACLsUpdatePlan->getNumberOfAdditions();
	}

	function getNumberOfUpdates(): int {
		return $this->groupfolderACLsUpdatePlan->getNumberOfUpdates();
	}

	function getNumberOfDeletions(): int {
		return $this->groupfolderACLsUpdatePlan->getNumberOfDeletions();
	}	

	private function getNumberOfNon0PermissionACLs(array $acls): int {
		$result = 0;

		foreach($acls as $acl) {
			if($acl->getPermissions() !== 0) {
				$result++;
			}
		}

		return $result;
	}

	private function getNumberOf0PermissionACLs(array $acls): int {
		$result = 0;

		foreach($acls as $acl) {
			if($acl->getPermissions() === 0) {
				$result++;
			}
		}

		return $result;
	}

	function getNumberOfEffectivePermissionsAdditions(): int {
		return $this->getNumberOfNon0PermissionACLs($this->groupfolderACLsUpdatePlan->toCreate);
	}

	function getNumberOfEffectivePermissionsUpdates(): int {
		return $this->getNumberOfNon0PermissionACLs($this->groupfolderACLsUpdatePlan->toUpdate);
	}

	function getNumberOfEffectivePermissionsDeletions(): int {
		return 
			// Removal of 0 Permissions ACLs effectively does not change anything as a default-deny always gets generated
			$this->getNumberOfNon0PermissionACLs($this->groupfolderACLsUpdatePlan->toRemove)
			// Changes to 0 Permissions are essentially deletions
			+ $this->getNumberOf0PermissionACLs($this->groupfolderACLsUpdatePlan->toUpdate);
	}

	private function getNumberOfUsersInNon0PermissionACLs(array $acls): int {
		$result = 0;

		foreach($acls as $acl) {
			if($acl->getPermissions() !== 0) {
				$result += $this->getNumberOfUsersInUserMapping($acl->getUserMapping());
			}
		}

		return $result;
	}

	private function getNumberOfUsersIn0PermissionACLs(array $acls): int {
		$result = 0;

		foreach($acls as $acl) {
			if($acl->getPermissions() === 0) {
				$result += $this->getNumberOfUsersInUserMapping($acl->getUserMapping());
			}
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
		return $this->getNumberOfUsersInNon0PermissionACLs($this->groupfolderACLsUpdatePlan->toCreate);
	}

	function getNumberOfUsersWithPermissionsPotentiallyChanged(): int {
		return $this->getNumberOfUsersInNon0PermissionACLs($this->groupfolderACLsUpdatePlan->toUpdate);
	}

	function getNumberOfUsersWithPermissionsPotentiallyDeleted(): int {
		return
			// Removal of 0 Permissions ACLs effectively does not change anything as a default-deny always gets generated
			$this->getNumberOfUsersInNon0PermissionACLs($this->groupfolderACLsUpdatePlan->toRemove)
			// Changes to 0 Permissions are essentially deletions
			+ $this->getNumberOfUsersIn0PermissionACLs($this->groupfolderACLsUpdatePlan->toUpdate);
	}

	function apply(): void {
		$this->aclManager->applyUpdatePlan($this->groupfolderACLsUpdatePlan);
	}
}