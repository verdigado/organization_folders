<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCP\IGroupManager;

use OCA\OrganizationFolders\Manager\ACLManager;
use OCA\OrganizationFolders\Db\FolderResource;

class ResourcePermissionsApplyPlanFactory {
	public function __construct(
		protected readonly ACLManager $aclManager,
		protected readonly IGroupManager $groupManager,
	) {}

	public function buildPlan(ResourcePermissionsList $permissionsList): ResourcePermissionsApplyPlan {
		$resource = $permissionsList->getResource();

		if($resource instanceof FolderResource) {
			$groupfolderAclsUpdatePlan = $this->aclManager->createUpdatePlanFromAclList($permissionsList->toGroupfolderAclList());

			return new FolderResourcePermissionsApplyPlan($this->aclManager, $this->groupManager, $resource, $groupfolderAclsUpdatePlan);
		} else {
			throw new \Exception("Invalid resource type");
		}
	}
}