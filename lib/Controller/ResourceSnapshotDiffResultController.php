<?php

namespace OCA\OrganizationFolders\Controller;

use Psr\Container\ContainerInterface;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\App\IAppManager;

use OCA\GroupfolderFilesystemSnapshots\Manager\SnapshotManager;
use OCA\GroupfolderFilesystemSnapshots\Service\DiffTaskService;
use OCA\GroupfolderFilesystemSnapshots\Service\DiffTaskResultService;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Errors\ResourceSnapshotDiffTaskResultNotFound;

class ResourceSnapshotDiffResultController extends BaseController {
	use Errors;

	private bool $snapshotIntegrationEnabled;
	private readonly SnapshotManager $snapshotManager;
	private readonly DiffTaskService $diffTaskService;
	private readonly DiffTaskResultService $diffTaskResultService;

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
			$this->diffTaskService = $this->container->get(DiffTaskService::class);
			$this->diffTaskResultService = $this->container->get(DiffTaskResultService::class);
		}
	}

	private function findTaskResultIfAccessAllowed(int $resourceId, string $snapshotId, int $diffTaskId, int $diffTaskResultId) {
		$resource = $this->resourceService->find($resourceId);

		$this->denyAccessUnlessGranted(['UPDATE'], $resource);

        $taskResult = $this->diffTaskResultService->find($diffTaskResultId);

		$task = $this->diffTaskService->find($taskResult->getTaskId(), $this->userId);

		if($taskResult->getTaskId() !== $diffTaskId || $task->getGroupfolderId() !== $resource->getOrganizationFolderId() || $task->getSnapshotId() !== $snapshotId) {
			throw new ResourceSnapshotDiffTaskResultNotFound(['snapshotId' => $snapshotId, 'diffTaskId' => $diffTaskId, "diffTaskResultId" => $diffTaskResultId]);
        }

		return $taskResult;
	}

    #[NoAdminRequired]
    public function show(int $resourceId, string $snapshotId, int $diffTaskId, int $diffTaskResultId): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId, $snapshotId, $diffTaskId, $diffTaskResultId) {
			return $this->findTaskResultIfAccessAllowed($resourceId, $snapshotId, $diffTaskId, $diffTaskResultId);
		});
    }

    #[NoAdminRequired]
	public function revert(int $resourceId, string $snapshotId, int $diffTaskId, int $diffTaskResultId): JSONResponse {
		return $this->handleNotFound(function () use ($resourceId, $snapshotId, $diffTaskId, $diffTaskResultId) {
			$taskResult = $this->findTaskResultIfAccessAllowed($resourceId, $snapshotId, $diffTaskId, $diffTaskResultId);
			return $this->diffTaskResultService->revert($taskResult->getId(), $this->userId);
		});
    }
}