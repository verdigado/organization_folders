<?php

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Traits\ApiObjectController;

class ResourceController extends BaseController {
	use Errors;
	use ApiObjectController;

	public const MEMBERS_INCLUDE = 'members';

	public function __construct(
		private ResourceService $service,
		private ResourceMemberService $memberService,
		private string $userId,
    ) {
		parent::__construct();
	}

	private function getApiObjectFromEntity(Resource $resource, ?string $include): array {
		$includes = $this->parseIncludesString($include);

		$result = [];

		if ($this->shouldInclude(self::MODEL_INCLUDE, $includes)) {
			$result = $resource->jsonSerialize();
		}

		if ($this->shouldInclude(self::MEMBERS_INCLUDE, $includes)) {
			$result["members"] = $this->memberService->findAll($resource->getId());
		}

		return $result;
	}

    #[NoAdminRequired]
	public function show(int $resourceId, ?string $include): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId, $include) {
            $resource = $this->service->find($resourceId);

            $this->denyAccessUnlessGranted(['READ'], $resource);

			return $this->getApiObjectFromEntity($resource, $include);
		});
	}

    #[NoAdminRequired]
	public function create(
		int $organizationFolderId,
		string $type,
		string $name,
		?int $parentResourceId = null,
		bool $active = true,
		bool $inheritManagers = true,

		// for type folder
		?int $membersAclPermission = null,
		?int $managersAclPermission = null,
		?int $inheritedAclPermission = null,

		?string $include,
	): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $type, $name, $parentResourceId, $active, $inheritManagers, $membersAclPermission, $managersAclPermission, $inheritedAclPermission, $include) {
			if(!is_null($parentResourceId)) {
				$parentResource = $this->service->find($parentResourceId);

				$this->denyAccessUnlessGranted(['CREATE_SUBRESOURCE'], $parentResource);
			} else {
				// TODO: ask future organization folder voter
			}

			$resource = $this->service->create(
				organizationFolderId: $organizationFolderId,
				type: $type,
				name: $name,
				parentResourceId: $parentResourceId,
				active: $active,
				inheritManagers: $inheritManagers,

				membersAclPermission: $membersAclPermission,
				managersAclPermission: $managersAclPermission,
				inheritedAclPermission: $inheritedAclPermission,
			);

			return $this->getApiObjectFromEntity($resource, $include);
		});
	}

	#[NoAdminRequired]
	public function update(
		int $resourceId,
		?string $name = null,
		?bool $active = null,
		?bool $inheritManagers = null,

		// for type folder
		?int $membersAclPermission = null,
		?int $managersAclPermission = null,
		?int $inheritedAclPermission = null,

		?string $include,
	): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $name, $active, $inheritManagers, $membersAclPermission, $managersAclPermission, $inheritedAclPermission, $include) {
			$resource = $this->service->find($resourceId);
			
			$this->denyAccessUnlessGranted(['UPDATE'], $resource);

			$resource = $this->service->update(
				id: $resourceId,
				name: $name,
				active: $active,
				inheritManagers: $inheritManagers,

				membersAclPermission: $membersAclPermission,
				managersAclPermission: $managersAclPermission,
				inheritedAclPermission: $inheritedAclPermission,
			);

			return $this->getApiObjectFromEntity($resource, $include);
		});
	}
}