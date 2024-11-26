<?php

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\PrincipalFactory;

class ResourceMemberController extends BaseController {
	use Errors;

	public function __construct(
		private ResourceMemberService $service,
		private ResourceService $resourceService,
		private PrincipalFactory $principalFactory,
		private string $userId,
    ) {
		parent::__construct();
	}

    #[NoAdminRequired]
	public function index(int $resourceId): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId) {
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
	): JSONResponse {
		return $this->handleErrors(function () use ($id, $permissionLevel): ResourceMember {
			$resourceMember = $this->service->find($id);

			$resource = $this->resourceService->find($resourceMember->getResourceId());
			
			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $resource);

			$resourceMember = $this->service->update(
				id: $resourceMember->getId(),
				permissionLevel: ResourceMemberPermissionLevel::fromNameOrValue($permissionLevel),
			);

			return $resourceMember;
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->handleNotFound(function () use ($id): ResourceMember {
			$resourceMember = $this->service->find($id);

			$resource = $this->resourceService->find($resourceMember->getResourceId());
			
			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $resource);

			return $this->service->delete($resourceMember->getId());
		});
	}
}