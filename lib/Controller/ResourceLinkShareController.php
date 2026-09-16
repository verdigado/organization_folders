<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Service\AuthorizationService;
use OCA\OrganizationFolders\Service\ResourceLinkShareService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Model\ResourceLinkShare;

class ResourceLinkShareController extends BaseController {
	use Errors;

	public function __construct(
		AuthorizationService $authorizationService,
		private readonly ResourceLinkShareService $service,
		private readonly ResourceService $resourceService,
	) {
		parent::__construct($authorizationService);
	}

	#[NoAdminRequired]
	public function index(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "READ_LINK_SHARES");

			return $this->service->findAllByResourceId($resourceId);
		});
	}

	#[NoAdminRequired]
	public function create(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId): ResourceLinkShare {
			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted($resource, "UPDATE_LINK_SHARES");

			return $this->service->create($resource);
		});
	}

	#[NoAdminRequired]
	public function destroy(int $resourceId, int $id): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $id): ResourceLinkShare {
			$resource = $this->resourceService->find($resourceId);
			
			$this->denyAccessUnlessGranted($resource, "UPDATE_LINK_SHARES");

			return $this->service->delete($resource, $id);
		});
	}
}