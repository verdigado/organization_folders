<?php

namespace OCA\OrganizationFolders\Controller;

use Exception;
use Psr\Container\ContainerInterface;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\App\IAppManager;

use OCA\GroupfolderFilesystemSnapshots\Manager\SnapshotManager;
use OCA\GroupfolderFilesystemSnapshots\Service\DiffTaskService;
use OCA\GroupfolderFilesystemSnapshots\Service\DiffTaskResultService;
use OCA\GroupfolderFilesystemSnapshots\Db\DiffTask;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Errors\Api\SnapshotIntegrationNotActive;
use OCA\OrganizationFolders\Errors\Api\ResourceSnapshotNotFound;
use OCA\OrganizationFolders\Errors\Api\ResourceSnapshotDiffTaskNotFound;
use OCA\OrganizationFolders\Http\StreamedProgressResponse;

class ResourceSnapshotDiffController extends BaseController {
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
        private readonly ?string $userId,
	) {
		parent::__construct($authorizationService, $validatorService);

		$this->snapshotIntegrationEnabled = $this->appManager->isEnabledForUser("groupfolder_filesystem_snapshots");

		if($this->snapshotIntegrationEnabled) {
			$this->snapshotManager = $this->container->get(SnapshotManager::class);
			$this->diffTaskService = $this->container->get(DiffTaskService::class);
			$this->diffTaskResultService = $this->container->get(DiffTaskResultService::class);
		}
	}

    use Errors;

    #[NoAdminRequired]
    public function create(int $resourceId, string $snapshotId, bool $streamed = false, bool $includeResults = false): JSONResponse|StreamedProgressResponse {
		return $this->handleErrorsWithoutResponseWrapping(function () use ($resourceId, $snapshotId, $streamed, $includeResults) {
			if(!$this->snapshotIntegrationEnabled) {
				throw new SnapshotIntegrationNotActive();
			}

			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted(['RESTORE_FROM_SNAPSHOT'], $resource);	

			if(!$this->snapshotManager->snapshotExists($snapshotId)) {
				throw new ResourceSnapshotNotFound(['resourceId' => $resourceId, 'snapshotId' => $snapshotId]);
			}

			$groupFolderRelativePath = implode("/", $this->resourceService->getResourcePath($resource));

			$blocklist = $this->createSubresourceBlocklist($resource);			

			if($streamed) {
				$previousProgress = 0;
				return new StreamedProgressResponse(function() use ($resource, $groupFolderRelativePath, $snapshotId, $includeResults, $blocklist) {
					echo "[\n";

					try {
						$this->diffTaskService->create($groupFolderRelativePath, $resource->getOrganizationFolderId(), $snapshotId, $this->userId, $blocklist, function(array $progress) use ($includeResults, &$previousProgress) {
							if(($progress["progress"] >= $previousProgress + 0.1) || ($progress["progress"] === 1.0)) {
								$previousProgress = $progress["progress"];
								
								if($includeResults && isset($progress["result"])) {
									$progress["result"] = $this->addResultsToDiffTask($progress["result"]);
								}
								
								echo json_encode($progress) . (($progress["progress"] < 1.0) ? ",\n" : "\n]");
							}
						});
					} catch(Exception $e) {
						echo json_encode([
							"status" => "error",
							"errorMessage" => $e->getMessage(),
						]) . "\n]";
					}
				});
			} else {
				$task = $this->diffTaskService->create($groupFolderRelativePath, $resource->getOrganizationFolderId(), $snapshotId, $this->userId, $blocklist);

				if(!isset($task)) {
					throw new Exception("Unknown Error");
				}

				if($includeResults) {
					$task = $this->addResultsToDiffTask($task);
				}
				
				return new JSONResponse($task);
			}
		});
    }

    #[NoAdminRequired]
    public function show(int $resourceId, string $snapshotId, int $diffTaskId): JSONResponse {
		return $this->handleErrors(function () use ($resourceId, $snapshotId, $diffTaskId) {
			if(!$this->snapshotIntegrationEnabled) {
				throw new SnapshotIntegrationNotActive();
			}

			$resource = $this->resourceService->find($resourceId);

			$this->denyAccessUnlessGranted(['RESTORE_FROM_SNAPSHOT'], $resource);

			$task = $this->diffTaskService->find($diffTaskId, $this->userId);

			if($task->getGroupfolderId() === $resource->getOrganizationFolderId() && $task->getSnapshotId() === $snapshotId) {
				return $task;
			} else {
				throw new ResourceSnapshotDiffTaskNotFound(["resourceId" => $resourceId, "snapshotId" => $snapshotId, "diffTaskId" => $diffTaskId]);
			}
		});
    }

    private function addResultsToDiffTask(DiffTask $task): Array {
        $taskArray = $task->jsonSerialize();
        $taskArray["results"] = $this->diffTaskResultService->findAll($taskArray["id"]);
        return $taskArray;
    }

	private function createSubresourceBlocklist(Resource $resource) {
		$blocklist = [];

		$subresources = $this->resourceService->getSubResources($resource, ["type" => "folder"]);

		foreach($subresources as $subresource) {
			if($subresource->getActive() && $this->authorizationService->isGranted(["RESTORE_FROM_SNAPSHOT"], $subresource)) {
				$subBlocklist = $this->createSubresourceBlocklist($subresource);

				if(isset($subBlocklist) && count($subBlocklist) > 0) {
					$blocklist[$subresource->getName()] = $subBlocklist;
				}
			} else {
				$blocklist[$subresource->getName()] = true;
			}
		}

		return $blocklist;
	}
}