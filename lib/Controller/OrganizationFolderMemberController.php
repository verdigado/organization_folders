<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Service\AuthorizationService;
use OCA\OrganizationFolders\Db\OrganizationFolderMember;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Errors\Api\AccessDenied;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Model\PrincipalFactory;

class OrganizationFolderMemberController extends BaseController {
	use Errors;

	public function __construct(
		AuthorizationService $authorizationService,
		private OrganizationFolderMemberService $service,
		private OrganizationFolderService $organizationFolderService,
		private PrincipalFactory $principalFactory,
	) {
		parent::__construct($authorizationService);
	}

	#[NoAdminRequired]
	public function index(int $organizationFolderId): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId) {
			try {
				$organizationFolder = $this->organizationFolderService->find($organizationFolderId);
			} catch (OrganizationFolderNotFound $e) {
				// treat ids where user has no permissions and invalid ids the same
				throw new AccessDenied();
			}

			$apiPermissionsScratchpad = [];

			$this->denyAccessUnlessGranted($organizationFolder, "READ_MEMBERS", $apiPermissionsScratchpad);

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
			try {
				$organizationFolder = $this->organizationFolderService->find($organizationFolderId);
			} catch (OrganizationFolderNotFound $e) {
				// treat ids where user has no permissions and invalid ids the same
				throw new AccessDenied();
			}

			$apiPermissionsScratchpad = [];

			$this->denyAccessUnlessGranted($organizationFolder, "UPDATE_MEMBERS", $apiPermissionsScratchpad);

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

			$apiPermissionsScratchpad = [];
			
			$this->denyAccessUnlessGranted($organizationFolder, "UPDATE_MEMBERS", $apiPermissionsScratchpad);

			// TODO: implement cancelIfRevokesOwnManagementRights like ResourceMemberController

			$organizationFolderMember = $this->service->update(
				id: $organizationFolderMember->getId(),
				permissionLevel: OrganizationFolderMemberPermissionLevel::fromNameOrValue($permissionLevel),
			);

			return $organizationFolderMember;
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->handleErrors(function () use ($id): OrganizationFolderMember {
			$organizationFolderMember = $this->service->find($id);

			$organizationFolder = $this->organizationFolderService->find($organizationFolderMember->getOrganizationFolderId());

			$apiPermissionsScratchpad = [];
			
			$this->denyAccessUnlessGranted($organizationFolder, "UPDATE_MEMBERS", $apiPermissionsScratchpad);

			// TODO: implement cancelIfRevokesOwnManagementRights like ResourceMemberController

			return $this->service->delete($organizationFolderMember->getId());
		});
	}
}