<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Traits\ApiObjectController;
use OCA\OrganizationFolders\Errors\AccessDenied;

class ResourceController extends BaseController {
	use Errors;
	use ApiObjectController;

	public const PERMISSIONS_INCLUDE = 'permissions';
	public const MEMBERS_INCLUDE = 'members';
	public const SUBRESOURCES_INCLUDE = 'subresources';

	public function __construct(
		private ResourceService $service,
		private ResourceMemberService $memberService,
		private OrganizationFolderService $organizationFolderService, 
		private string $userId,
	) {
		parent::__construct();
	}

	private function getApiObjectFromEntity(Resource $resource, bool $limited, ?string $include): array {
		$includes = $this->parseIncludesString($include);

		$result = [];

		if ($this->shouldInclude(self::MODEL_INCLUDE, $includes)) {
			if($limited) {
				$result =  $resource->limitedJsonSerialize();
			} else {
				$result = $resource->jsonSerialize();
			}
		}

		if ($this->shouldInclude(self::PERMISSIONS_INCLUDE, $includes)) {
			$result["permissions"] = [];

			if($limited) {
				$result["permissions"]["level"] = "limited";
			} else {
				$result["permissions"]["level"] = "full";
			}
		}

		if(!$limited) {
			if ($this->shouldInclude(self::MEMBERS_INCLUDE, $includes)) {
				$result["members"] = $this->memberService->findAll($resource->getId());
			}
		}

		if($this->shouldInclude(self::SUBRESOURCES_INCLUDE, $includes)) {
			$result["subResources"] = $this->getSubResources($resource->getId());
		}

		return $result;
	}

	#[NoAdminRequired]
	public function show(int $resourceId, ?string $include): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId, $include) {
			$resource = $this->service->find($resourceId);

			if($this->authorizationService->isGranted(["READ"], $resource)) {
				$limited = false;
			} else if($this->authorizationService->isGranted(["READ_LIMITED"], $resource)) {
				$limited = true;
			} else {
				throw new AccessDenied();
			}

			return $this->getApiObjectFromEntity($resource, $limited, $include);
		});
	}

	#[NoAdminRequired]
	public function create(
		int $organizationFolderId,
		string $type,
		string $name,
		?int $parentResourceId = null,
		bool $active = true,
		bool $inheritManagers = true,

		// for type folder
		?int $membersAclPermission = null,
		?int $managersAclPermission = null,
		?int $inheritedAclPermission = null,

		?string $include,
	): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $type, $name, $parentResourceId, $active, $inheritManagers, $membersAclPermission, $managersAclPermission, $inheritedAclPermission, $include) {
			$organizationFolder = $this->organizationFolderService->find($organizationFolderId);
			
			if(!is_null($parentResourceId)) {
				$parentResource = $this->service->find($parentResourceId);

				$this->denyAccessUnlessGranted(['CREATE_SUBRESOURCE'], $parentResource);
			} else {
				$this->denyAccessUnlessGranted(['CREATE_TOP_LEVEL_RESOURCE'], $organizationFolder);
			}

			$resource = $this->service->create(
				organizationFolderId: $organizationFolder->getId(),
				type: $type,
				name: $name,
				parentResourceId: $parentResourceId,
				active: $active,
				inheritManagers: $inheritManagers,

				membersAclPermission: $membersAclPermission,
				managersAclPermission: $managersAclPermission,
				inheritedAclPermission: $inheritedAclPermission,
			);

			return $this->getApiObjectFromEntity($resource, false, $include);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $resourceId,
		?string $name = null,
		?bool $active = null,
		?bool $inheritManagers = null,

		// for type folder
		?int $membersAclPermission = null,
		?int $managersAclPermission = null,
		?int $inheritedAclPermission = null,

		?string $include,
	): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $name, $active, $inheritManagers, $membersAclPermission, $managersAclPermission, $inheritedAclPermission, $include) {
			$resource = $this->service->find($resourceId);
			
			$this->denyAccessUnlessGranted(['UPDATE'], $resource);

			$resource = $this->service->update(
				id: $resourceId,
				name: $name,
				active: $active,
				inheritManagers: $inheritManagers,

				membersAclPermission: $membersAclPermission,
				managersAclPermission: $managersAclPermission,
				inheritedAclPermission: $inheritedAclPermission,
			);

			return $this->getApiObjectFromEntity($resource, false, $include);
		});
	}
	
	#[NoAdminRequired]
	public function destroy(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->service->find($resourceId);
			
			$this->denyAccessUnlessGranted(['DELETE'], $resource);

			return $this->service->delete($resource);
		});
	}


	#[NoAdminRequired]
	public function subResources(int $resourceId): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId) {
			return $this->getSubResources($resourceId);
		});
	}

	protected function getSubResources(int $resourceId): array {
		$resource = $this->service->find($resourceId);
		$organizationFolder = $this->organizationFolderService->find($resource->getOrganizationFolderId());

		$subresources = $this->service->getSubResources($resource);

		$result = [];

		if($this->authorizationService->isGranted(['MANAGE_ALL_RESOURCES'], $organizationFolder)) {
			/* fastpath: access to all subresources */
			$result = $subresources;
		} else {			
			foreach($subresources as $subresource) {
				// Future optimization potential 1: the following will potentially check the permissions of each of these subresources all the way up the resource tree.
				// As sibling resources these subresources share the same resources above them in the tree.
				// So if access to the parent resource is granted, all subresources with inheritManagers can be granted immediately.
				// For all other subresources only a check if user has direct (non-inherited) manager rights is neccessary.

				// Future optimization potential 2: READ permission check checks MANAGE_ALL_RESOURCES again, at this point we know this to be false, because of the fastpath.
				// Could be replaced with something like a READ_DIRECT (name TBD) permission check, which does not check this again.
				if($this->authorizationService->isGranted(['READ'], $resource)) {
					$result[] = $subresource;
				} else if($this->authorizationService->isGranted(['READ_LIMITED'], $resource)) {
					$result[] = $subresource->limitedJsonSerialize();
				}
			}
		}

		return $result;
	}

	#[NoAdminRequired]
	public function findGroupMemberOptions(int $resourceId, string $search = '', int $limit = 20): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $search, $limit) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $resource);

			$options = $this->memberService->findGroupMemberOptions($resourceId, $search, $limit);

			return array_map(fn (\OCP\IGroup $group) => [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			], $options);
		});
	}

	#[NoAdminRequired]
	public function findUserMemberOptions(int $resourceId, string $search = '', int $limit = 20): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $search, $limit) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $resource);

			$options = $this->memberService->findUserMemberOptions($resourceId, $search, $limit);

			return array_map(fn (\OCP\IUser $user) => [
				'id' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'subname' => $user->getEMailAddress(),
			], $options);
		});
	}
}