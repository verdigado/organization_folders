<?php

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;

use OCA\GroupfolderFilesystemSnapshots\Manager\SnapshotManager;
use OCA\GroupfolderFilesystemSnapshots\Manager\PathManager;
use OCA\GroupfolderFilesystemSnapshots\Entity\Snapshot;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Errors\ResourceSnapshotNotFound;
use OCA\OrganizationFolders\Errors\SnapshotIntegrationNotActive;
use OCA\OrganizationFolders\Errors\ResourceDoesNotSupportSnapshots;

class ResourceSnapshotController extends BaseController {
	use Errors;

	private bool $snapshotIntegrationEnabled;

	private readonly SnapshotManager $snapshotManager;
	private readonly PathManager $pathManager;

	public function __construct(
		AuthorizationService $authorizationService,
		ValidatorService $validatorService,
		private readonly IAppManager $appManager,
		private readonly ContainerInterface $container,
		private readonly ResourceService $resourceService,
        private ?string $userId,
	) {
		parent::__construct($authorizationService, $validatorService);

		$this->snapshotIntegrationEnabled = $this->appManager->isEnabledForUser("groupfolder_filesystem_snapshots");

		if($this->snapshotIntegrationEnabled) {
			$this->snapshotManager = $this->container->get(SnapshotManager::class);
			$this->pathManager = $this->container->get(PathManager::class);
		}
	}

    use Errors;

    #[NoAdminRequired]
    public function index(int $resourceId): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId) {
			if(!$this->snapshotIntegrationEnabled) {
				throw new SnapshotIntegrationNotActive();
			}

			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted(['RESTORE_FROM_SNAPSHOT'], $resource);

			if($resource->getType() !== "folder") {
				throw new ResourceDoesNotSupportSnapshots($resource);
			}

			$resourcePath = implode(DIRECTORY_SEPARATOR, $this->resourceService->getResourcePath($resource));

			$snapshots = [];

			foreach ($this->snapshotManager->getFilteredGenerator($resource->getOrganizationFolderId(), $resourcePath) as $snapshot) {
				if(($snapshot->getCreatedTimestamp() !== null) && $snapshot->getCreatedTimestamp()->getTimestamp() > $resource->getCreatedTimestamp()) {
					$snapshots[] = $snapshot;
				}
			}

			return $snapshots;
		});
    }

    #[NoAdminRequired]
    public function show(int $resourceId, string $snapshotId): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId, $snapshotId): Snapshot {
			if(!$this->snapshotIntegrationEnabled) {
				throw new SnapshotIntegrationNotActive();
			}

			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted(['RESTORE_FROM_SNAPSHOT'], $resource);

			if($resource->getType() !== "folder") {
				throw new ResourceDoesNotSupportSnapshots($resource);
			}

			$snapshot = $this->snapshotManager->get($snapshotId);

			if(!$snapshot) {
				throw new ResourceSnapshotNotFound(['resourceId' => $resourceId, 'snapshotId' => $snapshotId]);
			}

			$resourcePath = implode(DIRECTORY_SEPARATOR, $this->resourceService->getResourcePath($resource));

			$testDir = $this->pathManager->getGroupFolderSnapshotDirectory($resource->getOrganizationFolderId(), $resourcePath, $snapshotId);

			if(!(is_dir($testDir) && ($snapshot->getCreatedTimestamp() !== null) && ($snapshot->getCreatedTimestamp()->getTimestamp() > $resource->getCreatedTimestamp()))) {
				throw new ResourceSnapshotNotFound(['resourceId' => $resourceId, 'snapshotId' => $snapshotId]);
			}

			return $snapshot;
		});
    }
}