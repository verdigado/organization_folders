<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\IUserManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Traits\ApiObjectController;
use OCA\OrganizationFolders\Errors\Api\AccessDenied;
use OCA\OrganizationFolders\Errors\Api\WouldRevokeUsersManagementPermissions;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Enum\PrincipalType;

class ResourceController extends BaseController {
	use Errors;
	use ApiObjectController;

	public const PERMISSIONS_INCLUDE = 'permissions';
	public const MEMBERS_INCLUDE = 'members';
	public const SUBRESOURCES_INCLUDE = 'subresources';
	public const UNMANAGEDSUBFOLDERS_INCLUDE = 'unmanagedSubfolders';
	public const FULLPATH_INCLUDE = 'fullPath';

	public function __construct(
		AuthorizationService $authorizationService,
		ValidatorService $validatorService,
		private readonly ResourceService $service,
		private readonly ResourceMemberService $memberService,
		private readonly OrganizationFolderService $organizationFolderService,
		private readonly PrincipalFactory $principalFactory,
		private readonly IUserManager $userManager,
		private string $userId,
	) {
		parent::__construct($authorizationService, $validatorService);
	}

	private function getApiObjectFromEntity(Resource $resource, bool $limited, ?string $include = null): array {
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

		if($this->shouldInclude(self::SUBRESOURCES_INCLUDE, $includes)) {
			$result["subResources"] = $this->getSubResources($resource);
		}

		if($this->shouldInclude(self::FULLPATH_INCLUDE, $includes)) {
			$result["fullPath"] = [];

			$fullPathResources = $this->service->getAllResourcesOnPathFromRootToResource($resource);

			foreach($fullPathResources as $resource) {
				$result["fullPath"][] = [
					"id" => $resource->getId(),
					"name" => $resource->getName(),
				];
			}
		}

		if(!$limited) {
			if ($this->shouldInclude(self::MEMBERS_INCLUDE, $includes)) {
				$result["members"] = $this->memberService->findAll($resource->getId());
			}

			if($this->shouldInclude(self::UNMANAGEDSUBFOLDERS_INCLUDE, $includes)) {
				$result["unmanagedSubfolders"] = $this->service->getUnmanagedSubfolders($resource);
			}
		}

		return $result;
	}

	#[NoAdminRequired]
	public function show(int $resourceId, ?string $include = null): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $include) {
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

		?string $include = null,
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
		?bool $active = null,
		?bool $inheritManagers = null,

		// for type folder
		?int $membersAclPermission = null,
		?int $managersAclPermission = null,
		?int $inheritedAclPermission = null,

		?string $include = null,
		?int $cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove = null,
		?bool $cancelIfRevokesOwnManagementRights = false,
	): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $active, $inheritManagers, $membersAclPermission, $managersAclPermission, $inheritedAclPermission, $include, $cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove, $cancelIfRevokesOwnManagementRights) {
			$resource = $this->service->find($resourceId);
			
			$this->denyAccessUnlessGranted(['UPDATE'], $resource);

			if($cancelIfRevokesOwnManagementRights) {
				if($inheritManagers === false) {
					$organizationFolder = $this->organizationFolderService->find($resource->getOrganizationFolderId());

					// user has UPDATE, but neither MANAGE_ALL_RESOURCES nor READ_DIRECT, meaning they get their management permission via inheritance
					if(!($this->authorizationService->isGranted(["MANAGE_ALL_RESOURCES"], $organizationFolder) ||
						$this->authorizationService->isGranted(["READ_DIRECT"], $resource))) {
							throw new WouldRevokeUsersManagementPermissions();
					}
				}
			}

			$resource = $this->service->update(
				id: $resourceId,
				active: $active,
				inheritManagers: $inheritManagers,

				membersAclPermission: $membersAclPermission,
				managersAclPermission: $managersAclPermission,
				inheritedAclPermission: $inheritedAclPermission,

				maxiumumUsersPermissionsAddedOrDeleted: $cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove,
			);

			return $this->getApiObjectFromEntity($resource, false, $include);
		});
	}

	#[NoAdminRequired]
	public function move(
		int $resourceId,
		string $name,
		?int $parentResourceId,

		?string $include = null,
	): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $name, $parentResourceId, $include) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted(['UPDATE'], $resource);

			// only allow moving to places where the user is allowed to create resources
			if(isset($parentResourceId)) {
				$newParentResource = $this->service->find($parentResourceId);
				$this->denyAccessUnlessGranted(['CREATE_SUBRESOURCE'], $newParentResource);
			} else {
				$organizationFolder = $this->organizationFolderService->find($resource->getOrganizationFolderId());
				$this->denyAccessUnlessGranted(['CREATE_TOP_LEVEL_RESOURCE'], $organizationFolder);
			}

			$resource = $this->service->move(
				resource: $resource,
				name: $name,
				parentResourceId: $parentResourceId,
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
	public function subResources(int $resourceId, ?string $include = null): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $include) {
			$resource = $this->service->find($resourceId);

			$this->authorizationService->isGranted(["READ", "READ_LIMITED"], $resource);

			return $this->getSubResources($resource, $include);
		});
	}

	protected function getSubResources(Resource $resource, ?string $include = null): array {
		$organizationFolder = $this->organizationFolderService->find($resource->getOrganizationFolderId());

		$subresources = $this->service->getSubResources($resource);

		$result = [];

		if($this->authorizationService->isGranted(['MANAGE_ALL_RESOURCES'], $organizationFolder)) {
			/* fastpath: access to all subresources */
			foreach($subresources as $subresource) {
				$result[] = $this->getApiObjectFromEntity($subresource, false, $include);
			}
		} else {
			foreach($subresources as $subresource) {
				// Future optimization potential 1: the following will potentially check the permissions of each of these subresources all the way up the resource tree.
				// As sibling resources these subresources share the same resources above them in the tree.
				// So if access to the parent resource is granted, all subresources with inheritManagers can be granted immediately.
				// For all other subresources only a check if user has direct (non-inherited) manager rights is neccessary.

				// Future optimization potential 2: READ permission check checks MANAGE_ALL_RESOURCES again, at this point we know this to be false, because of the fastpath.
				// Could be replaced with something like a READ_DIRECT (name TBD) permission check, which does not check this again.
				if($this->authorizationService->isGranted(['READ'], $subresource)) {
					$result[] = $this->getApiObjectFromEntity($subresource, false, $include);
				} else if($this->authorizationService->isGranted(['READ_LIMITED'], $subresource)) {
					$result[] = $this->getApiObjectFromEntity($subresource, true, $include);
				}
			}
		}

		return $result;
	}

	#[NoAdminRequired]
	public function unmanagedSubfolders(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted(['READ'], $resource);

			return $this->service->getUnmanagedSubfolders($resource);
		});
	}

	#[NoAdminRequired]
	public function promoteUnmanagedSubfolder(int $resourceId, string $unmanagedSubfolderName): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $unmanagedSubfolderName) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted(['CREATE_SUBRESOURCE'], $resource);

			return $this->service->promoteUnmanagedSubfolder($resource, $unmanagedSubfolderName);
		});
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

	#[NoAdminRequired]
	public function permissionsReport(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted(['READ'], $resource);

			return $this->service->getPermissionsReport($resource);
		});
	}

	#[NoAdminRequired]
	public function userPermissionsReport(int $resourceId, string $userId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $userId) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted(['READ'], $resource);

			$userPrincipal = $this->principalFactory->buildPrincipal(PrincipalType::USER, $userId);

			return $this->service->getUserPermissionsReport($resource, $userPrincipal);
		});
	}

	#[NoAdminRequired]
	public function findUserPermissionsReportOptions(int $resourceId, string $search = '', int $limit = 20): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $search, $limit) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted(['READ'], $resource);

			$options = array_values($this->userManager->search($search, $limit));

			return array_map(fn (\OCP\IUser $user) => [
				'id' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'subname' => $user->getEMailAddress(),
			], $options);
		});
	}
}