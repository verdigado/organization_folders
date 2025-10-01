<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Traits\ApiObjectController;
use OCA\OrganizationFolders\Errors\Api\AccessDenied;

class OrganizationFolderController extends BaseController {
	use Errors;
	use ApiObjectController;

	public const PERMISSIONS_INCLUDE = 'permissions';
	public const MEMBERS_INCLUDE = 'members';
	public const RESOURCES_INCLUDE = 'resources';
	public const QUOTAUSED_INCLUDE = 'quotaUsed';

	public function __construct(
		AuthorizationService $authorizationService,
		ValidatorService $validatorService,
		private OrganizationFolderService $service,
		private OrganizationFolderMemberService $memberService,
		private ResourceService $resourceService,
		private OrganizationProviderManager $organizationProviderManager,
		private string $userId,
	) {
		parent::__construct($authorizationService, $validatorService);
	}

	/* ADMIN ONLY */
	// TODO: add server-side pagination
	public function index(): JSONResponse {
		return new JSONResponse($this->service->findAll());
	}

	private function getApiObjectFromEntity(OrganizationFolder $organizationFolder, bool $limited, ?string $include): array {
		$includes = $this->parseIncludesString($include);

		$result = [];

		if ($this->shouldInclude(self::MODEL_INCLUDE, $includes)) {
			if($limited) {
				$result =  $organizationFolder->limitedJsonSerialize();
			} else {
				$result = $organizationFolder->jsonSerialize();
			}

			if($organizationFolder->getOrganizationProvider() && $organizationFolder->getOrganizationId()) {
				try {
					$organizationProvider = $this->organizationProviderManager->getOrganizationProvider($organizationFolder->getOrganizationProvider());
					$organization = $organizationProvider->getOrganization($organizationFolder->getOrganizationId());

					$organizationFullHierarchy = [$organization];

					while($organization?->getParentOrganizationId() && $organization = $organizationProvider->getOrganization($organization->getParentOrganizationId())) {
						$organizationFullHierarchy[] = $organization;
					}

					$result["organizationFullHierarchy"] = array_reverse($organizationFullHierarchy);
					$result["organizationProviderFriendlyName"] = $organizationProvider->getFriendlyName();
				} catch (\Throwable $e) {
					$result["organizationFullHierarchy"] = null;
					$result["organizationProviderFriendlyName"] = null;
				}
			} else {
				$result["organizationFullHierarchy"] = null;
				$result["organizationProviderFriendlyName"] = null;
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

		
		if ($this->shouldInclude(self::QUOTAUSED_INCLUDE, $includes)) {
			$result["quotaUsed"] = $this->service->getOrganizationFolderQuotaUsed($organizationFolder);
		}

		if(!$limited) {
			if ($this->shouldInclude(self::MEMBERS_INCLUDE, $includes)) {
				$result["members"] = $this->memberService->findAll($organizationFolder->getId());
			}
		}

		if($this->shouldInclude(self::RESOURCES_INCLUDE, $includes)) {
			$result["resources"] = $this->getResources($organizationFolder);
		}

		return $result;
	}

	#[NoAdminRequired]
	public function show(int $organizationFolderId, ?string $include): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $include) {
			$organizationFolder = $this->service->find($organizationFolderId);

			if($this->authorizationService->isGranted(["READ"], $organizationFolder)) {
				$limited = false;
			} else if($this->authorizationService->isGranted(["READ_LIMITED"], $organizationFolder)) {
				$limited = true;
			} else {
				throw new AccessDenied();
			}

			return $this->getApiObjectFromEntity($organizationFolder,  $limited, $include);
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
			$organizationFolder = $this->service->find($organizationFolderId);
			
			$this->denyAccessUnlessGranted(['UPDATE'], $organizationFolder);

			$organizationFolder = $this->service->update(
				id: $organizationFolderId,
				name: $name,
				quota: $quota,
				organizationProviderId: $organizationProviderId,
				organizationId: $organizationId,
			);

			return $this->getApiObjectFromEntity($organizationFolder, false, $include);
		});
	}

	#[NoAdminRequired]
	public function resources(int $organizationFolderId): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId) {
			$organizationFolder = $this->service->find($organizationFolderId);

			$this->denyAccessUnlessGranted(['READ', 'READ_LIMITED'], $organizationFolder);

			return $this->getResources($organizationFolder);
		});
	}

	protected function getResources(OrganizationFolder $organizationFolder): array {
		$resources = $this->resourceService->findAll($organizationFolder->getId());

		$result = [];

		if($this->authorizationService->isGranted(['MANAGE_ALL_RESOURCES'], $organizationFolder)) {
			/* fastpath: access to all resources */
			foreach($resources as $resource) {
				$result[] = [
					...$resource->jsonSerialize(),
					"permissions" => [
						"level" => "full",
					],
				];
			}
		} else {
			foreach($resources as $resource) {
				// Future optimization potential: READ permission check checks MANAGE_ALL_RESOURCES again, at this point we know this to be false, because of the fastpath.
				// Could be replaced with something like a READ_DIRECT (name TBD) permission check, which does not check this again.
				if($this->authorizationService->isGranted(['READ'], $resource)) {
					$result[] = [
						...$resource->jsonSerialize(),
						"permissions" => [
							"level" => "full",
						],
					];
				} else if($this->authorizationService->isGranted(['READ_LIMITED'], $resource)) {
					$result[] = [
						...$resource->limitedJsonSerialize(),
						"permissions" => [
							"level" => "limited",
						],
					];
				}
			}
		}

		return $result;
	}

	#[NoAdminRequired]
	public function findGroupMemberOptions(int $organizationFolderId, string $search = '', int $limit = 20): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $search, $limit) {
			$organizationFolder = $this->service->find($organizationFolderId);

			$this->denyAccessUnlessGranted(['UPDATE_MEMBERS'], $organizationFolder);

			$options = $this->memberService->findGroupMemberOptions($organizationFolderId, $search, $limit);

			return array_map(fn (\OCP\IGroup $group) => [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			], $options);
		});
	}
}