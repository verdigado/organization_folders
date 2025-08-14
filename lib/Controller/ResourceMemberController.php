<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\IDBConnection;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Db\TTransactional;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Errors\Api\WouldRevokeUsersManagementPermissions;

class ResourceMemberController extends BaseController {
	use Errors;
	use TTransactional;

	public function __construct(
		AuthorizationService $authorizationService,
		ValidatorService $validatorService,
		protected readonly IDBConnection $db,
		private ResourceMemberService $service,
		private ResourceService $resourceService,
		private PrincipalFactory $principalFactory,
		private string $userId,
	) {
		parent::__construct($authorizationService, $validatorService);
	}

	#[NoAdminRequired]
	public function index(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted(['READ'], $resource);

			return $this->service->findAll($resourceId);
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

			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $resource);

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
			
			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $resource);

			return $this->atomic(function () use ($resource, $resourceMember, $permissionLevel, $cancelIfRevokesOwnManagementRights) {
				// TODO: move applying resource permissions after check if rollback will be needed
				$resourceMember = $this->service->update(
					id: $resourceMember->getId(),
					permissionLevel: ResourceMemberPermissionLevel::fromNameOrValue($permissionLevel),
				);

				if($cancelIfRevokesOwnManagementRights && !$this->authorizationService->isGranted(["READ"], $resource)) {
					throw new WouldRevokeUsersManagementPermissions();
				}

				return $resourceMember;
			}, $this->db);
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
			
			$this->denyAccessUnlessGranted(["UPDATE_MEMBERS"], $resource);

			return $this->atomic(function () use ($resource, $resourceMember, $cancelIfRevokesOwnManagementRights) {
				// TODO: move applying resource permissions after check if rollback will be needed
				$resourceMember = $this->service->delete($resourceMember->getId());

				if($cancelIfRevokesOwnManagementRights && !$this->authorizationService->isGranted(["READ"], $resource)) {
					throw new WouldRevokeUsersManagementPermissions();
				}

				return $resourceMember;
			}, $this->db);
		});
	}
}