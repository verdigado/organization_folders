<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\IUserManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Service\AuthorizationService;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\DTO\CreateResourceDto;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Traits\ApiResponseController;
use OCA\OrganizationFolders\Errors\Api\AccessDenied;
use OCA\OrganizationFolders\Errors\Api\WouldRevokeUsersManagementPermissions;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Errors\Api\ResourceNotFound;
use OCA\OrganizationFolders\Model\ApiResponse\ResourceResponseFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\ResourceInheritedManagerCriterionWrapper;

class ResourceController extends BaseController {
	use Errors;
	use ApiResponseController;

	public function __construct(
		AuthorizationService $authorizationService,
		private readonly ResourceService $service,
		private readonly ResourceMemberService $memberService,
		private readonly OrganizationFolderService $organizationFolderService,
		private readonly PrincipalFactory $principalFactory,
		private readonly IUserManager $userManager,
		private readonly ResourceResponseFactory $resourceResponseFactory,
	) {
		parent::__construct($authorizationService);
	}

	/**
	 * The API object is the object returned as the response
	 * It combines multiple entities and only returns fields the user is allowed to access
	 * 
	 * @param Resource $resource
	 * @param string $include
	 * @param array $apiPermissionsScratchpad
	 * @param bool $throwIfNoAccess if false returns empty object instead of throwing
	 * 
	 * @return array
	 */
	private function getApiResponseFromEntity(Resource $resource, ?string $include, array &$apiPermissionsScratchpad, bool $throwIfNoAccess = true): array {
		$includes = $this->parseIncludesString($include);

		return $this->resourceResponseFactory->buildResponseForOne($resource, $includes, $apiPermissionsScratchpad, $throwIfNoAccess);
	}

	#[NoAdminRequired]
	public function show(int $resourceId, ?string $include = null): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $include) {
			$resource = $this->service->find($resourceId);

			$apiPermissionsScratchpad = [];

			return $this->getApiResponseFromEntity($resource, $include, $apiPermissionsScratchpad);
		});
	}

	#[NoAdminRequired]
	public function create(
		int $organizationFolderId,
		string $type,
		string $name,
		array $memberPermissions,
		array $managerPermissions,
		array $inheritedMemberPermissions,
		?int $parentResourceId = null,
		bool $active = true,
		bool $inheritManagers = true,

		?string $include = null,
	): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $type, $name, $parentResourceId, $active, $inheritManagers, $memberPermissions, $managerPermissions, $inheritedMemberPermissions, $include) {
			try {
				$organizationFolder = $this->organizationFolderService->find($organizationFolderId);
			} catch (OrganizationFolderNotFound $e) {
				// treat ids where user has no permissions and invalid ids the same
				throw new AccessDenied();
			}

			$apiPermissionsScratchpad = [];
			
			if($parentResourceId !== null) {
				$parentResource = $this->service->find($parentResourceId);

				$this->denyAccessUnlessGranted($parentResource, "CREATE_SUBRESOURCE", $apiPermissionsScratchpad);
			} else {
				$this->denyAccessUnlessGranted($organizationFolder, "CREATE_TOP_LEVEL_RESOURCE", $apiPermissionsScratchpad);
			}

			$resource = $this->service->create(
				organizationFolderId: $organizationFolder->getId(),
				type: $type,
				name: $name,
				parentResourceId: $parentResourceId,
				active: $active,
				inheritManagers: $inheritManagers,
				memberPermissions: $memberPermissions,
				managerPermissions: $managerPermissions,
				inheritedMemberPermissions: $inheritedMemberPermissions,
			);

			return $this->getApiResponseFromEntity($resource, $include, $apiPermissionsScratchpad);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $resourceId,
		?bool $active = null,
		?bool $inheritManagers = null,
		?array $memberPermissions = null,
		?array $managerPermissions = null,
		?array $inheritedMemberPermissions = null,

		?string $include = null,
		?int $cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove = null,
		?bool $cancelIfRevokesOwnManagementRights = false,
	): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $active, $inheritManagers, $memberPermissions, $managerPermissions, $inheritedMemberPermissions, $include, $cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove, $cancelIfRevokesOwnManagementRights) {
			$resource = $this->service->find($resourceId);

			$apiPermissionsScratchpad = [];
			
			$this->denyAccessUnlessGranted($resource, "UPDATE", $apiPermissionsScratchpad);

			if($inheritManagers === false) {
				$revokesOwnUpdatePermission = !$this->authorizationService->isGranted(
					$resource,
					"UPDATE",
					$apiPermissionsScratchpad,
					[ResourceInheritedManagerCriterionWrapper::CRITERION_TYPE => true]
				);
				if($cancelIfRevokesOwnManagementRights && $revokesOwnUpdatePermission) {
					throw new WouldRevokeUsersManagementPermissions();
				}
			}

			$resource = $this->service->update(
				id: $resourceId,
				active: $active,
				inheritManagers: $inheritManagers,
				memberPermissions: $memberPermissions,
				managerPermissions: $managerPermissions,
				inheritedMemberPermissions: $inheritedMemberPermissions,

				maxiumumUsersPermissionsAddedOrDeleted: $cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove,
			);

			// Clear scratchpad after making changes that can potentially impact permissions
			$apiPermissionsScratchpad = [];

			return $this->getApiResponseFromEntity($resource, $include, $apiPermissionsScratchpad, false);
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

			$apiPermissionsScratchpad = [];

			$this->denyAccessUnlessGranted($resource, "UPDATE", $apiPermissionsScratchpad);

			if($parentResourceId !== $resource->getParentResourceId()) {
				// only allow moving to places where the user is allowed to create resources
				if(isset($parentResourceId)) {
					try {
						$newParentResource = $this->service->find($parentResourceId);
					} catch (ResourceNotFound $e) {
						throw new AccessDenied();
					}
					$this->denyAccessUnlessGranted($newParentResource, "CREATE_SUBRESOURCE", $apiPermissionsScratchpad);
				} else {
					$organizationFolder = $this->organizationFolderService->find($resource->getOrganizationFolderId());
					$this->denyAccessUnlessGranted($organizationFolder, "CREATE_TOP_LEVEL_RESOURCE", $apiPermissionsScratchpad);
				}
			}

			$resource = $this->service->move(
				resource: $resource,
				name: $name,
				parentResourceId: $parentResourceId,
			);

			// Clear scratchpad after making changes that can potentially impact permissions
			$apiPermissionsScratchpad = [];

			return $this->getApiResponseFromEntity($resource, $include, $apiPermissionsScratchpad, false);
		});
	}
	
	#[NoAdminRequired]
	public function destroy(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->service->find($resourceId);
			
			$this->denyAccessUnlessGranted($resource, "DELETE");

			return $this->service->delete($resource);
		});
	}


	#[NoAdminRequired]
	public function subResources(int $resourceId, ?string $include = null): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $include) {
			$resource = $this->service->find($resourceId);

			$apiPermissionsScratchpad = [];

			$this->denyAccessUnlessGranted($resource, "READ_LIMITED", $apiPermissionsScratchpad);

			$subResources = $this->service->getSubResources($resource);

			$includes = $this->parseIncludesString($include);

			return $this->resourceResponseFactory->buildResponseForMultiple($subResources, $includes, $apiPermissionsScratchpad);
		});
	}

	#[NoAdminRequired]
	public function unmanagedSubfolders(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "READ");

			return $this->service->getUnmanagedSubfolders($resource);
		});
	}

	#[NoAdminRequired]
	public function promoteUnmanagedSubfolder(int $resourceId, string $unmanagedSubfolderName): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $unmanagedSubfolderName) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "CREATE_SUBRESOURCE");

			return $this->service->promoteUnmanagedSubfolder($resource, $unmanagedSubfolderName);
		});
	}

	#[NoAdminRequired]
	public function findGroupMemberOptions(int $resourceId, string $search = '', int $limit = 20): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $search, $limit) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "UPDATE_MEMBERS");

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

			$this->denyAccessUnlessGranted($resource, "UPDATE_MEMBERS");

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

			$this->denyAccessUnlessGranted($resource, "GET_PERMISSIONS_REPORT");

			return $this->service->getPermissionsReport($resource);
		});
	}

	#[NoAdminRequired]
	public function userPermissionsReport(int $resourceId, string $userId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $userId) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "GET_PERMISSIONS_REPORT");

			$userPrincipal = $this->principalFactory->buildPrincipal(PrincipalType::USER, $userId);

			return $this->service->getUserPermissionsReport($resource, $userPrincipal);
		});
	}

	#[NoAdminRequired]
	public function findUserPermissionsReportOptions(int $resourceId, string $search = '', int $limit = 20): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $search, $limit) {
			$resource = $this->service->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "GET_PERMISSIONS_REPORT");

			$options = array_values($this->userManager->search($search, $limit));

			return array_map(fn (\OCP\IUser $user) => [
				'id' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'subname' => $user->getEMailAddress(),
			], $options);
		});
	}
}