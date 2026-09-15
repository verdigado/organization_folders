<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\IDBConnection;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Db\TTransactional;

use OCA\OrganizationFolders\Service\AuthorizationService;
use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Errors\Api\WouldRevokeUsersManagementPermissions;

class ResourceMemberController extends BaseController {
	use Errors;
	use TTransactional;

	public function __construct(
		AuthorizationService $authorizationService,
		private readonly IDBConnection $db,
		private readonly ResourceMemberService $service,
		private readonly ResourceService $resourceService,
		private readonly OrganizationFolderService $organizationFolderService,
		private readonly PrincipalFactory $principalFactory,
	) {
		parent::__construct($authorizationService);
	}

	#[NoAdminRequired]
	public function index(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "READ_MEMBERS");

			return $this->service->findAll([
				"resourceId" => $resourceId,
			]);
		});
	}

	#[NoAdminRequired]
	public function create(
		int $resourceId,
		string|int $permissionLevel,
		string|int $principalType,
		string $principalId,
	): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $permissionLevel, $principalType, $principalId): ResourceMember {
			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "UPDATE_MEMBERS");

			$principal = $this->principalFactory->buildPrincipal(PrincipalType::fromNameOrValue($principalType), $principalId);

			$resourceMember = $this->service->create(
				resourceId: $resourceId,
				permissionLevel: ResourceMemberPermissionLevel::fromNameOrValue($permissionLevel),
				principal: $principal,
			);

			return $resourceMember;
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		string|int $permissionLevel,
		?bool $cancelIfRevokesOwnManagementRights = false,
	): JSONResponse {
		return $this->handleErrors(function () use ($id, $permissionLevel, $cancelIfRevokesOwnManagementRights): ResourceMember {
			$resourceMember = $this->service->find($id);

			$resource = $this->resourceService->find($resourceMember->getResourceId());

			$apiPermissionsScratchpad = [];
			
			$this->denyAccessUnlessGranted($resource, "UPDATE_MEMBERS", $apiPermissionsScratchpad);

			$resourceMember = $this->atomic(function () use ($resource, $resourceMember, $permissionLevel, $cancelIfRevokesOwnManagementRights) {
				$resourceMember = $this->service->update(
					id: $resourceMember->getId(),
					permissionLevel: ResourceMemberPermissionLevel::fromNameOrValue($permissionLevel),
					skipPermssionsApply: true,
				);

				// checking READ grant after changes without re-using old scratchpad(!)
				if($cancelIfRevokesOwnManagementRights && !$this->authorizationService->isGranted($resource, "READ")) {
					throw new WouldRevokeUsersManagementPermissions();
				}

				return $resourceMember;
			}, $this->db);

			// apply permissions only after knowing request was not cancelled
			$this->organizationFolderService->applyAllPermissionsById($resource->getOrganizationFolderId());

			return $resourceMember;
		});
	}

	#[NoAdminRequired]
	public function destroy(
		int $id,
		?bool $cancelIfRevokesOwnManagementRights = false,
	): JSONResponse {
		return $this->handleErrors(function () use ($id, $cancelIfRevokesOwnManagementRights): ResourceMember {
			$resourceMember = $this->service->find($id);

			$resource = $this->resourceService->find($resourceMember->getResourceId());

			$apiPermissionsScratchpad = [];
			
			$this->denyAccessUnlessGranted($resource, "UPDATE_MEMBERS", $apiPermissionsScratchpad);

			$resourceMember = $this->atomic(function () use ($resource, $resourceMember, $cancelIfRevokesOwnManagementRights) {
				$resourceMember = $this->service->delete(
					id: $resourceMember->getId(),
					skipPermssionsApply: true,
				);

				if($cancelIfRevokesOwnManagementRights && !$this->authorizationService->isGranted($resource, "READ")) {
					throw new WouldRevokeUsersManagementPermissions();
				}

				return $resourceMember;
			}, $this->db);

			// apply permissions only after knowing request was not cancelled
			$this->organizationFolderService->applyAllPermissionsById($resource->getOrganizationFolderId());

			return $resourceMember;
		});
	}
}