<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Dav;

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\ICollection;

use OCP\Files\Folder;
use OCP\Files\DavUtil;

use OCA\DAV\Connector\Sabre\Node;
use OCA\DAV\Connector\Sabre\FilesPlugin;
use OCA\GroupFolders\Mount\GroupMountPoint;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\AuthorizationService;

class PropFindPlugin extends ServerPlugin {
	public const ORGANIZATION_FOLDER_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-id';
	public const ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-resource-id';
	public const ORGANIZATION_FOLDER_READ_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-user-has-read-permissions';
	public const ORGANIZATION_FOLDER_READ_LIMITED_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-user-has-read-limited-permissions';
	public const ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-user-has-update-permissions';
	public const ORGANIZATION_FOLDER_RESOURCE_READ_LIMITED_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-resource-user-has-read-limited-permissions';
	public const ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-resource-user-has-update-permissions';

	private array $apiPermissionsScratchpad = [];

	private array $organizationFolderByNodeIdCache = [];
	private array $organizationFolderByIdCache = [];
	private array $folderResourceByNodeIdCache = [];

	public function __construct(
		private OrganizationFolderService $organizationFolderService,
		private ResourceService $resourceService,
		private AuthorizationService $authorizationService,
	) {
	}

	public function initialize(Server $server): void {
		$server->on('preloadCollection', $this->preloadCollection(...));

		// priority 90 ensures we get asked before the dav apps FilesPlugin, so we can reduce the permissions if necessary
		$server->on('propFind', $this->propFind(...), 90);
	}

	public function clearCaches(): void {
		$this->apiPermissionsScratchpad = [];
		$this->organizationFolderByNodeIdCache = [];
		$this->organizationFolderByIdCache = [];
		$this->folderResourceByNodeIdCache = [];
	}

	private function getOrganizationFolderFromNode(Folder $node) {
		$nodeId = $node->getId();

		if(isset($this->organizationFolderByNodeIdCache[$nodeId])) {
			return $this->organizationFolderByNodeIdCache[$nodeId];
		}

		$mountPoint = $node->getMountPoint();

		if ($mountPoint instanceof GroupMountPoint) {
			$groupFolderId = $mountPoint->getFolderId();

			if(isset($this->organizationFolderByIdCache[$groupFolderId])) {
				return $this->organizationFolderByIdCache[$groupFolderId];
			}
			
			return $this->organizationFolderByNodeIdCache[$nodeId] = $this->organizationFolderByIdCache[$groupFolderId] = $this->organizationFolderService->find($groupFolderId);
		} else {
			throw new OrganizationFolderNotFound(["path" => $node->getPath()]);
		}
	}

	private function getFolderResourceFromNode(Folder $node) {
		$nodeId = $node->getId();

		if(isset($this->folderResourceByNodeIdCache[$nodeId])) {
			return $this->folderResourceByNodeIdCache[$nodeId];
		}

		return $this->folderResourceByNodeIdCache[$nodeId] = $this->resourceService->findByFilesystemNode($node, true);
	}

	private function getFolderLevel(string $internalPath): int {
		return  count(array_filter(
			array: explode('/', $internalPath),
			callback: fn($part) => $part !== ''
		));
	}

	private function preloadCollection(PropFind $propFind, ICollection $collection): void {
		if(!($collection instanceof \OCA\DAV\Connector\Sabre\Directory)) {
			return;
		}

		if($collection instanceof \OCA\DAV\Files\FilesHome) {
			// nothing to preload as we do not know which organizationFolders will be loaded
			return;
		}

        $neededFor = [
            self::ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME,
            self::ORGANIZATION_FOLDER_RESOURCE_READ_LIMITED_PERMISSIONS_PROPERTYNAME,
			self::ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME,
			FilesPlugin::PERMISSIONS_PROPERTYNAME,
        ];

        $anyRequested = array_reduce(
            $neededFor,
            fn($result, $property) => $result || $propFind->getStatus($property) !== null,
            false,
        );

        if (!$anyRequested) {
            return;
        }

		$node = $collection->getNode();

		if(!($node instanceof Folder)) {
			return;
		}

		try {
			$organizationFolder = $this->getOrganizationFolderFromNode($node);
		} catch(\Exception $e) {
			return;
		}

		try {
			$resource = $this->getFolderResourceFromNode($node);
		} catch(\Exception $e) {
			$resource = null;
		}

		try {
			$preloadedFolderSubResources = $this->resourceService->findAll($organizationFolder->getId(), $resource?->getId(), ["type" => "folder"]);

			foreach($preloadedFolderSubResources as $folderResource) {
				$this->folderResourceByNodeIdCache[$folderResource->getFileId()] = $folderResource;
			}
		} catch (\Exception $e) {
			// preload failed for some reason, continue without preload
			return;
		}
    }

	private function propFind(PropFind $propFind, INode $sabreNode): void {
		if (!$sabreNode instanceof Node) {
			return;
		}

		$node = $sabreNode->getNode();

		if (!$node instanceof Folder) {
			return;
		}

		$fileInfo = $sabreNode->getFileInfo();
		$mount = $fileInfo->getMountPoint();

		if (!$mount instanceof GroupMountPoint) {
			return;
		}

		$internalPath = $mount->getInternalPath($node->getPath());

		$folderLevel = $this->getFolderLevel($internalPath);

		$isInOrganizationFolder = null;

		if($folderLevel === 0) {
			$isResource = false;
		} else {
			$isResource = null;
		}

		/**
		 * @var ?OrganizationFolder
		 */
		$organizationFolder = null;

		/**
		 * @var ?Resource
		 */
		$resource = null;

		$propFind->handle(self::ORGANIZATION_FOLDER_ID_PROPERTYNAME, function () use (&$node, &$isInOrganizationFolder, &$organizationFolder): ?int {
			try {
				if(!isset($organizationFolder)) {
					$organizationFolder = $this->getOrganizationFolderFromNode($node);
				}

				$isInOrganizationFolder = true;
			} catch (\Exception $e) {
				$isInOrganizationFolder = false;

				return null;
			}

			return $organizationFolder->getId();
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_READ_PERMISSIONS_PROPERTYNAME, function () use (&$node, $folderLevel, &$isInOrganizationFolder, &$organizationFolder): ?string {
			if($folderLevel > 0) {
				return null;
			}

			if($isInOrganizationFolder === false) {
				return null;
			}

			if(!isset($organizationFolder)) {
				try {
					$organizationFolder = $this->getOrganizationFolderFromNode($node);
					$isInOrganizationFolder = true;
				} catch (\Exception $e) {
					$isInOrganizationFolder = false;

					return null;
				}
			}

			try {
				return $this->authorizationService->isGranted($organizationFolder, "READ", $this->apiPermissionsScratchpad) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_READ_LIMITED_PERMISSIONS_PROPERTYNAME, function () use (&$node, $folderLevel, &$isInOrganizationFolder, &$organizationFolder): ?string {
			if($folderLevel > 0) {
				return null;
			}

			if($isInOrganizationFolder === false) {
				return null;
			}

			if(!isset($organizationFolder)) {
				try {
					$organizationFolder = $this->getOrganizationFolderFromNode($node);
					$isInOrganizationFolder = true;
				} catch (\Exception $e) {
					$isInOrganizationFolder = false;

					return null;
				}
			}

			try {
				return $this->authorizationService->isGranted($organizationFolder, "READ_LIMITED", $this->apiPermissionsScratchpad) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME, function () use (&$node, $folderLevel, &$isInOrganizationFolder, &$organizationFolder): ?string {
			if($folderLevel > 0) {
				return null;
			}

			if($isInOrganizationFolder === false) {
				return null;
			}

			if(!isset($organizationFolder)) {
				try {
					$organizationFolder = $this->getOrganizationFolderFromNode($node);
					$isInOrganizationFolder = true;
				} catch (\Exception $e) {
					$isInOrganizationFolder = false;

					return null;
				}
			}

			try {
				return $this->authorizationService->isGranted($organizationFolder, "UPDATE", $this->apiPermissionsScratchpad) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME, function () use ($node, &$isInOrganizationFolder, &$isResource, &$resource): ?int {	
			if($isInOrganizationFolder === false) {
				return null;
			}

			if($isResource === false) {
				return null;
			}

			if(!isset($resource)) {
				try {
					$resource = $this->getFolderResourceFromNode($node);
					$isInOrganizationFolder = true;
					$isResource = true;
				} catch (\Exception $e) {
					$isResource = false;

					return null;
				}
			}

			return $resource->getId();
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_RESOURCE_READ_LIMITED_PERMISSIONS_PROPERTYNAME, function () use ($node, &$isInOrganizationFolder, &$isResource, &$resource): ?string {
			if($isInOrganizationFolder === false) {
				return null;
			}

			if($isResource === false) {
				return null;
			}
			
			if(!isset($resource)) {
				try {
					$resource = $this->getFolderResourceFromNode($node);
					$isInOrganizationFolder = true;
					$isResource = true;
				} catch (\Exception $e) {
					$isResource = false;

					return null;
				}
			}

			try {
				return $this->authorizationService->isGranted($resource, "READ_LIMITED", $this->apiPermissionsScratchpad) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME, function () use ($node, &$isInOrganizationFolder, &$isResource, &$resource): ?string {
			if($isInOrganizationFolder === false) {
				return null;
			}

			if($isResource === false) {
				return null;
			}
			
			if(!isset($resource)) {
				try {
					$resource = $this->getFolderResourceFromNode($node);
					$isInOrganizationFolder = true;
					$isResource = true;
				} catch (\Exception $e) {
					$isResource = false;

					return null;
				}
			}

			try {
				return $this->authorizationService->isGranted($resource, "UPDATE", $this->apiPermissionsScratchpad) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(FilesPlugin::PERMISSIONS_PROPERTYNAME, function () use ($node, &$isInOrganizationFolder, &$isResource, &$resource): string {
			if(!isset($resource)) {
				try {
					$resource = $this->getFolderResourceFromNode($node);
					$isInOrganizationFolder = true;
					$isResource = true;
				} catch (\Exception $e) {
					$isResource = false;
				}
			}

			$permissions = DavUtil::getDavPermissions($node->getFileInfo());

			if($isResource) {
				// deletions are not actually possible
				$filteredPermissions = str_replace('D', '', $permissions);
				// renames are not actually possible
				$filteredPermissions = str_replace('N', '', $filteredPermissions);
				// moves are not actually possible
				$filteredPermissions = str_replace('V', '', $filteredPermissions);

				return $filteredPermissions;
			} else {
				return $permissions;
			}
		});
	}
}