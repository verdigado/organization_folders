<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ApiResponse;

use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Service\AuthorizationService;
use OCA\OrganizationFolders\Service\ResourceLinkShareService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Service\ResourceService;

class ResourceResponseFactory extends ApiResponseFactory {

	public const USER_API_PERMISSIONS_INCLUDE = 'userApiPermissions';
	public const MEMBERS_INCLUDE = 'members';
	public const PARENT_RESOURCE_INCLUDE = "parentResource";
	public const SUBRESOURCES_INCLUDE = 'subresources';
	public const UNMANAGEDSUBFOLDERS_INCLUDE = 'unmanagedSubfolders';
	public const FULLPATH_INCLUDE = 'fullPath';
	public const LINK_SHARES_INCLUDE = "linkShares";

	public function __construct(
		private readonly AuthorizationService $authorizationService,
		private readonly ResourceService $resourceService,
		private readonly ResourceMemberService $resourceMemberService,
		private readonly ResourceLinkShareService $linkShareService,
	) {
		$this->setIncludeHandlers([
			self::MODEL_INCLUDE => $this->includeModel(...),
			self::USER_API_PERMISSIONS_INCLUDE => $this->includeUserApiPermissions(...),
			self::MEMBERS_INCLUDE => $this->includeMembers(...),
			self::PARENT_RESOURCE_INCLUDE => $this->includeParentResource(...),
			self::SUBRESOURCES_INCLUDE => $this->includeSubResources(...),
			self::UNMANAGEDSUBFOLDERS_INCLUDE => $this->includeUnmanagedSubfolders(...),
			self::LINK_SHARES_INCLUDE => $this->includeLinkShares(...),
			self::FULLPATH_INCLUDE => $this->includeFullPath(...),
		]);
	}

	protected function filter(mixed $entity, array $apiPermissionsScratchpad): bool {
		if($entity instanceof Resource) {
			return $this->authorizationService->isGrantedAny($entity, ["READ", "READ_LIMITED"], $apiPermissionsScratchpad);
		} else {
			// invalid call
			return false;
		}
	}

	private function includeModel(Resource $resource, array &$apiPermissionsScratchpad, array &$response): void {
		if($this->authorizationService->isGranted($resource, "READ", $apiPermissionsScratchpad)) {
			$response += $resource->jsonSerialize();
		} else if($this->authorizationService->isGranted($resource, "READ_LIMITED", $apiPermissionsScratchpad)) {
			$response += $resource->limitedJsonSerialize();
		} else {
			return;
		}
	}

	private function includeUserApiPermissions(Resource $resource, array &$apiPermissionsScratchpad, array &$response): void {
		$response["userApiPermissions"] = $this->authorizationService->decideMultipleActions(
			$resource,
			["READ", "READ_LIMITED", "UPDATE", "DELETE", "GET_PERMISSIONS_REPORT", "READ_MEMBERS", "UPDATE_MEMBERS", "READ_LINK_SHARES", "UPDATE_LINK_SHARES", "CREATE_SUBRESOURCE", "RESTORE_FROM_SNAPSHOT"],
			$apiPermissionsScratchpad
		);
	}

	private function includeMembers(Resource $resource, array &$apiPermissionsScratchpad, array &$response): void {
		if($this->authorizationService->isGranted($resource, "READ_MEMBERS", $apiPermissionsScratchpad)) {
			$response["members"] = $this->resourceMemberService->findAll(["resourceId" => $resource->getId()]);
		} else {
			$response["members"] = null;
		}
	}

	public function includeParentResource(Resource $resource, array &$apiPermissionsScratchpad, array &$response): void {
		if($resource->getParentResourceId() === null) {
			$response["parentResource"] = null;
		} else {
			$parentResource = $this->resourceService->getParentResource($resource);
			$response["parentResource"] = $this->buildResponseForOne($parentResource, [self::MODEL_INCLUDE], $apiPermissionsScratchpad);
		}
	}

	public function includeSubResources(Resource $resource, array &$apiPermissionsScratchpad, array &$response): void {
		if(!$resource::SUPPORTS_SUBRESOURCES) {
			$response["subResources"] = null;
		} else {
			// TODO: allow passing subIncludes in API
			$subIncludes = [self::MODEL_INCLUDE, self::USER_API_PERMISSIONS_INCLUDE];

			$subResources = $this->resourceService->getSubResources($resource);

			$response["subResources"] = $this->buildResponseForMultiple($subResources, $subIncludes, $apiPermissionsScratchpad);
		}
	}

	public function includeUnmanagedSubfolders(Resource $resource, array &$apiPermissionsScratchpad, array &$response): void {
		if($resource instanceof FolderResource) {
			if($this->authorizationService->isGranted($resource, "READ", $apiPermissionsScratchpad)) {
				$response["unmanagedSubfolders"] = $this->resourceService->getUnmanagedSubfolders($resource);
				return;
			}
		}

		$response["unmanagedSubfolders"] = null;
	}

	private function includeLinkShares(Resource $resource, array &$apiPermissionsScratchpad, array &$response): void {
		if ($resource::SUPPORTS_LINK_SHARES) {
			if($this->authorizationService->isGranted($resource, "READ_LINK_SHARES", $apiPermissionsScratchpad)) {
				$response["linkShares"] = $this->linkShareService->findAll($resource);
			}
		}
	}

	private function includeFullPath(Resource $resource, array &$apiPermissionsScratchpad, array &$response): void {
		$fullPathResources = $this->resourceService->getAllResourcesOnPathFromRootToResource($resource);

		$response["fullPath"] = $this->buildResponseForMultiple($fullPathResources, [self::MODEL_INCLUDE, self::USER_API_PERMISSIONS_INCLUDE], $apiPermissionsScratchpad);
	}
}