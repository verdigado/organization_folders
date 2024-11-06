<?php

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Service\ResourceService;

class ResourceController extends BaseController {
	use Errors;

	public function __construct(
		private ResourceService $service,
		private string $userId,
    ) {
		parent::__construct();
	}

    #[NoAdminRequired]
	public function show(int $resourceId): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId) {
            $resource = $this->service->find($resourceId);

            $this->denyAccessUnlessGranted(['READ'], $resource);

			return $resource;
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
	): JSONResponse {
		return $this->handleErrors(function () use ($organizationFolderId, $type, $name, $parentResourceId, $active, $inheritManagers, $membersAclPermission, $managersAclPermission, $inheritedAclPermission) {
			// TODO: check permissions

			return $this->service->create(
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
		});
	}
}