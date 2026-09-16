<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ApiResponse;

use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Service\AuthorizationService;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\ResourceService;

class OrganizationFolderResponseFactory extends ApiResponseFactory {
	public const QUOTA_USED_INCLUDE = 'quotaUsed';
	public const USER_API_PERMISSIONS_INCLUDE = 'userApiPermissions';
	public const MEMBERS_INCLUDE = 'members';
	public const RESOURCES_INCLUDE = 'resources';

	public function __construct(
		private readonly AuthorizationService $authorizationService,
		private readonly OrganizationFolderService $organizationFolderService,
		private readonly OrganizationProviderManager $organizationProviderManager,
		private readonly OrganizationFolderMemberService $organizationFolderMemberService,
		private readonly ResourceService $resourceService,
	) {
		$this->setIncludeHandlers([
            self::MODEL_INCLUDE => $this->includeModel(...),
			self::QUOTA_USED_INCLUDE => $this->includeQuotaUsed(...),
			self::USER_API_PERMISSIONS_INCLUDE => $this->includeUserApiPermissions(...),
			self::MEMBERS_INCLUDE => $this->includeMembers(...),
			self::RESOURCES_INCLUDE => $this->includeResources(...),
        ]);
	}

	protected function filter(mixed $entity, array $apiPermissionsScratchpad): bool {
		if($entity instanceof OrganizationFolder) {
			return $this->authorizationService->isGrantedAny($entity, ["READ", "READ_LIMITED"], $apiPermissionsScratchpad);
		} else {
			// invalid call
			return false;
		}
	}

	private function includeModel(OrganizationFolder $organizationFolder, array &$apiPermissionsScratchpad, array &$response): void {
		if($this->authorizationService->isGranted($organizationFolder, "READ", $apiPermissionsScratchpad)) {
			$response += $organizationFolder->jsonSerialize();
		} else if($this->authorizationService->isGranted($organizationFolder, "READ_LIMITED", $apiPermissionsScratchpad)) {
			$response += $organizationFolder->limitedJsonSerialize();
		} else {
			return;
		}

		if($organizationFolder->getOrganizationProviderId() && $organizationFolder->getOrganizationId()) {
			try {
				$organizationProvider = $this->organizationProviderManager->getOrganizationProvider($organizationFolder->getOrganizationProviderId());
				$organization = $organizationProvider->getOrganization($organizationFolder->getOrganizationId());

				$organizationFullHierarchy = [$organization];

				while($organization?->getParentOrganizationId() && $organization = $organizationProvider->getOrganization($organization->getParentOrganizationId())) {
					$organizationFullHierarchy[] = $organization;
				}

				$response["organizationFullHierarchy"] = array_reverse($organizationFullHierarchy);
				$response["organizationProviderFriendlyName"] = $organizationProvider->getFriendlyName();
			} catch (\Throwable $e) {
				$response["organizationFullHierarchy"] = null;
				$response["organizationProviderFriendlyName"] = null;
			}
		} else {
			$response["organizationFullHierarchy"] = null;
			$response["organizationProviderFriendlyName"] = null;
		}
	}

	private function includeQuotaUsed(OrganizationFolder $organizationFolder, array &$apiPermissionsScratchpad, array &$response): void {
		$response["quotaUsed"] = $this->organizationFolderService->getOrganizationFolderQuotaUsed($organizationFolder);
	}

	private function includeUserApiPermissions(OrganizationFolder $organizationFolder, array &$apiPermissionsScratchpad, array &$response): void {
		$response["userApiPermissions"] = $this->authorizationService->decideMultipleActions($organizationFolder, ["READ", "READ_LIMITED", "UPDATE", "DELETE", "READ_MEMBERS", "UPDATE_MEMBERS", "CREATE_TOP_LEVEL_RESOURCE"], $apiPermissionsScratchpad);
	}

	private function includeMembers(OrganizationFolder $organizationFolder, array &$apiPermissionsScratchpad, array &$response): void {
		if($this->authorizationService->isGranted($organizationFolder, "READ_MEMBERS", $apiPermissionsScratchpad)) {
			$response["members"] = $this->organizationFolderMemberService->findAll($organizationFolder->getId());
		} else {
			$response["members"] = null;
		}
	}

	private function includeResources(OrganizationFolder $organizationFolder, array &$apiPermissionsScratchpad, array &$response): void {
		$resources = $this->resourceService->findAll($organizationFolder->getId());
		
		/**
		 * @var ResourceResponseFactory
		 */
		$resourceResponseFactory = \OC::$server->get(ResourceResponseFactory::class);

		// TODO: allow passing subIncludes in API
		$subIncludes = [self::MODEL_INCLUDE];

		$response["resources"] = $resourceResponseFactory->buildResponseForMultiple($resources, $subIncludes, $apiPermissionsScratchpad);
	}
}