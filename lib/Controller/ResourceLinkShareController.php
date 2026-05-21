<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Service\ResourceLinkShareService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\Model\ResourceLinkShare;

class ResourceLinkShareController extends BaseController {
	use Errors;

	public function __construct(
		AuthorizationService $authorizationService,
		ValidatorService $validatorService,
		private readonly ResourceLinkShareService $service,
		private readonly ResourceService $resourceService,
	) {
		parent::__construct($authorizationService, $validatorService);
	}

	#[NoAdminRequired]
	public function index(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId) {
			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted(['READ'], $resource);

			return $this->service->findAllByResourceId($resourceId);
		});
	}

	#[NoAdminRequired]
	public function create(int $resourceId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId): ResourceLinkShare {
			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted(['UPDATE_LINK_SHARES'], $resource);

			return $this->service->create($resource);
		});
	}

	#[NoAdminRequired]
	public function destroy(int $resourceId, int $id): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $id): ResourceLinkShare {
			$resource = $this->resourceService->find($resourceId);
			
			$this->denyAccessUnlessGranted(["UPDATE_LINK_SHARES"], $resource);

			return $this->service->delete($resource, $id);
		});
	}
}