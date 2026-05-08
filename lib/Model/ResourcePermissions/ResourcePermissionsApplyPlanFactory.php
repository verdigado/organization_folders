<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ResourcePermissions;

use OCP\IGroupManager;

use OCA\OrganizationFolders\Manager\ACLManager;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\CalendarResource;
use OCA\OrganizationFolders\Model\AclList;
use OCA\OrganizationFolders\Groups\GroupBackend;
use OCA\OrganizationFolders\Integration\Dav\CalendarIntegration;

use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\OrganizationFolders\Model\CalendarShareList;
use OCA\DAV\DAV\Sharing\Backend;

class ResourcePermissionsApplyPlanFactory {
	public function __construct(
		protected readonly ACLManager $aclManager,
		protected readonly CalendarIntegration $calendarIntegration,
		protected readonly IGroupManager $groupManager,
	) {}

	public function buildPlan(ResourcePermissionsList $permissionsList): ResourcePermissionsApplyPlan {
		$resource = $permissionsList->getResource();

		if($resource instanceof FolderResource) {
			$groupfolderAclsUpdatePlan = $this->aclManager->createUpdatePlanFromAclList($this->permissionsListToGroupfolderAclList($permissionsList));

			return new FolderResourcePermissionsApplyPlan($this->aclManager, $this->groupManager, $resource, $groupfolderAclsUpdatePlan);
		} else if($resource instanceof CalendarResource) {
			$calendarSharesUpdatePlan = $this->calendarIntegration->createSharesUpdatePlanFromShareList($this->permissionsListToCalendarShareList($permissionsList));

			return new CalendarResourcePermissionsApplyPlan($this->calendarIntegration, $this->groupManager, $resource, $calendarSharesUpdatePlan);
		} else {
			throw new \Exception("Invalid resource type");
		}
	}

	private function permissionsListToGroupfolderAclList(ResourcePermissionsList $permissionsList): AclList {
		$resource = $permissionsList->getResource();

		if(!($resource instanceof FolderResource)) {
			throw new \Exception("Only folder resources can be transformed to an AclList");
		}

		$acls = new AclList($resource->getFileId());

		// add default deny
		$acls->addRule(
			userMapping: new UserMapping(type: "group", id: GroupBackend::EVERYONE_GROUP, displayName: null),
			mask: 31,
			permissions: 0,
		);

		foreach($permissionsList->getPermissions() as $permission) {
			if($permission->getPermissionsBitmap() > 0) {
				$acls->addRule(
					userMapping: $permission->getPrincipal()->toGroupfolderAclMapping(),
					mask: 31,
					permissions: $permission->getPermissionsBitmap(),
				);
			}
		}

		return $acls;
	}

	private function permissionsListToCalendarShareList(ResourcePermissionsList $permissionsList): CalendarShareList {
		$resource = $permissionsList->getResource();

		if(!($resource instanceof CalendarResource)) {
			throw new \Exception("Only calendar resources can be transformed to a share list");
		}

		$shares = new CalendarShareList($resource->getCalendarId());

		foreach($permissionsList->getPermissions() as $permission) {
			if($permission->getPermissionsBitmap() === CalendarResource::PERMISSION_READ) {
				$shares->addShare(
					principaluri: $permission->getPrincipal()->toDavPrincipalURI(),
					access: Backend::ACCESS_READ,
				);
			} else if ($permission->getPermissionsBitmap() === (CalendarResource::PERMISSION_READ | CalendarResource::PERMISSION_UPDATE)) {
				$shares->addShare(
					principaluri: $permission->getPrincipal()->toDavPrincipalURI(),
					access: Backend::ACCESS_READ_WRITE,
				);
			}
		}

		return $shares;
	}
}