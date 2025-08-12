<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\Db\OrganizationFolderMember;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Model\PrincipalFactory;

class OrganizationFolderMemberController extends BaseController {
	use Errors;

	public function __construct(
		AuthorizationService $authorizationService,
		ValidatorService $validatorService,
		private OrganizationFolderMemberService $service,
		private OrganizationFolderService $organizationFolderService,
		private PrincipalFactory $principalFactory,
	) {
		parent::__construct($authorizationService, $validatorService);
	}

	#[NoAdminRequired]
	public function index(int $organizationFolderId): JSONResponse {
		return $this->handleNotFound(function () use ($organizationFolderId) {
			$organizationFolder = $this->organizationFolderService->find($organizationFolderId);

			$this->denyAccessUnlessGranted(['READ'], $organizationFolder);

			return $this->service->findAll($organizationFolderId);
		});
	}

	#[NoAdminRequired]
	public function create(
		int $organizationFolderId,
		string|int $permissionLevel,
		string|int $principalType,
		string $principalId,
	): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $permissionLevel, $principalType, $principalId): OrganizationFolderMember {
			$organizationFolder = $this->organizationFolderService->find($organizationFolderId);

			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $organizationFolder);

			$principal = $this->principalFactory->buildPrincipal(PrincipalType::fromNameOrValue($principalType), $principalId);

			$organizationFolderMember = $this->service->create(
				organizationFolder: $organizationFolder,
				permissionLevel: OrganizationFolderMemberPermissionLevel::fromNameOrValue($permissionLevel),
				principal: $principal,
			);

			return $organizationFolderMember;
		});
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		string|int $permissionLevel,
	): JSONResponse {
		return $this->handleErrors(function () use ($id, $permissionLevel): OrganizationFolderMember {
			$organizationFolderMember = $this->service->find($id);

			$organizationFolder = $this->organizationFolderService->find($organizationFolderMember->getOrganizationFolderId());
			
			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $organizationFolder);

			$organizationFolderMember = $this->service->update(
				id: $organizationFolderMember->getId(),
				permissionLevel: OrganizationFolderMemberPermissionLevel::fromNameOrValue($permissionLevel),
			);

			return $organizationFolderMember;
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->handleNotFound(function () use ($id): OrganizationFolderMember {
			$organizationFolderMember = $this->service->find($id);

			$organizationFolder = $this->organizationFolderService->find($organizationFolderMember->getOrganizationFolderId());
			
			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $organizationFolder);

			return $this->service->delete($organizationFolderMember->getId());
		});
	}
}