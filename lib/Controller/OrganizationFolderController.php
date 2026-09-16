<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Service\AuthorizationService;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Traits\ApiResponseController;
use OCA\OrganizationFolders\Errors\Api\AccessDenied;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Model\ApiResponse\OrganizationFolderResponseFactory;
use OCA\OrganizationFolders\Model\ApiResponse\ResourceResponseFactory;

class OrganizationFolderController extends BaseController {
	use Errors;
	use ApiResponseController;

	public const PERMISSIONS_INCLUDE = 'permissions';
	public const MEMBERS_INCLUDE = 'members';
	public const RESOURCES_INCLUDE = 'resources';
	public const QUOTAUSED_INCLUDE = 'quotaUsed';

	public function __construct(
		AuthorizationService $authorizationService,
		private readonly OrganizationFolderService $service,
		private readonly OrganizationFolderMemberService $memberService,
		private readonly ResourceService $resourceService,
		private readonly OrganizationFolderResponseFactory $organizationFolderResponseFactory,
		private readonly ResourceResponseFactory $resourceResponseFactory,
		private string $userId,
	) {
		parent::__construct($authorizationService);
	}

	/* ADMIN ONLY */
	// TODO: add server-side pagination
	public function index(): JSONResponse {
		return new JSONResponse($this->service->findAll());
	}

	/**
	 * The API object is the object returned as the response
	 * It combines multiple entities and only returns fields the user is allowed to access
	 * 
	 * @param OrganizationFolder $organizationFolder
	 * @param string $include
	 * @param array $apiPermissionsScratchpad
	 * @param bool $throwIfNoAccess if false returns empty object instead of throwing
	 * 
	 * @return array
	 */
	private function getApiResponseFromEntity(OrganizationFolder $organizationFolder, ?string $include, array &$apiPermissionsScratchpad, bool $throwIfNoAccess = true): array {
		$includes = $this->parseIncludesString($include);

		return $this->organizationFolderResponseFactory->buildResponseForOne($organizationFolder, $includes, $apiPermissionsScratchpad, $throwIfNoAccess);
	}

	#[NoAdminRequired]
	public function show(int $organizationFolderId, ?string $include): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $include) {
			try {
				$organizationFolder = $this->service->find($organizationFolderId);
			} catch (OrganizationFolderNotFound $e) {
				// treat ids where user has no permissions and invalid ids the same
				throw new AccessDenied();
			}
			
			$apiPermissionsScratchpad = [];

			return $this->getApiResponseFromEntity($organizationFolder, $include, $apiPermissionsScratchpad);
		});
	}

	/* ADMIN ONLY */
	public function create(
		string $name,
		?int $quota = null,
		?string $organizationProviderId = null,
		?int $organizationId = null,
	): JSONResponse {
		return $this->handleErrors(function () use ($name, $quota, $organizationProviderId, $organizationId) {
			$organizationFolder = $this->service->create(
				name: $name,
				quota: $quota,
				organizationProvider: $organizationProviderId,
				organizationId: $organizationId,
			);

			return $organizationFolder;
		});
	}

	#[NoAdminRequired]
	public function update(
		int $organizationFolderId,
		?string $name = null,
		?int $quota = null,
		?string $organizationProviderId = null,
		?int $organizationId = null,

		?string $include = null,
	): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $name, $quota, $organizationProviderId, $organizationId, $include) {
			try {
				$organizationFolder = $this->service->find($organizationFolderId);
			} catch (OrganizationFolderNotFound $e) {
				// treat ids where user has no permissions and invalid ids the same
				throw new AccessDenied();
			}

			$apiPermissionsScratchpad = [];

			$this->denyAccessUnlessGranted($organizationFolder, "UPDATE", $apiPermissionsScratchpad);

			$organizationFolder = $this->service->update(
				id: $organizationFolderId,
				name: $name,
				quota: $quota,
				organizationProviderId: $organizationProviderId,
				organizationId: $organizationId,
			);

			// Clear scratchpad after making changes that can potentially impact permissions
			// (In this case the built-in voters can't change their votes, but others might)
			$apiPermissionsScratchpad = [];

			return $this->getApiResponseFromEntity($organizationFolder, $include, $apiPermissionsScratchpad, false);
		});
	}

	#[NoAdminRequired]
	public function resources(int $organizationFolderId, ?string $include = null): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $include) {
			try {
				$organizationFolder = $this->service->find($organizationFolderId);
			} catch (OrganizationFolderNotFound $e) {
				// treat ids where user has no permissions and invalid ids the same
				throw new AccessDenied();
			}

			$apiPermissionsScratchpad = [];

			$this->denyAccessUnlessGranted($organizationFolder, "READ_LIMITED", $apiPermissionsScratchpad);

			$resources = $this->resourceService->findAll($organizationFolder->getId());

			$includes = $this->parseIncludesString($include);

			return $this->resourceResponseFactory->buildResponseForMultiple($resources, $includes, $apiPermissionsScratchpad);
		});
	}

	#[NoAdminRequired]
	public function findGroupMemberOptions(int $organizationFolderId, string $search = '', int $limit = 20): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $search, $limit) {
			try {
				$organizationFolder = $this->service->find($organizationFolderId);
			} catch (OrganizationFolderNotFound $e) {
				// treat ids where user has no permissions and invalid ids the same
				throw new AccessDenied();
			}

			$this->denyAccessUnlessGranted($organizationFolder, "UPDATE_MEMBERS");

			$options = $this->memberService->findGroupMemberOptions($organizationFolderId, $search, $limit);

			return array_map(fn (\OCP\IGroup $group) => [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			], $options);
		});
	}
}