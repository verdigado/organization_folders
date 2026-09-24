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
use OCP\IUserSession;

use OCA\DAV\Connector\Sabre\Directory;
use OCA\DAV\Connector\Sabre\FilesPlugin;
use OCA\DAV\CalDAV\Calendar;
use OCA\DAV\CalDAV\Publishing\PublishPlugin;
use OCA\GroupFolders\Mount\GroupMountPoint;

use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\CalendarResource;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\AuthorizationService;

class PropFindPlugin extends ServerPlugin {
	public const ORGANIZATION_FOLDER_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-id';
	public const ORGANIZATION_FOLDER_ORGANIZATION_PROVIDER_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-organization-provider-id';
	public const ORGANIZATION_FOLDER_ORGANIZATION_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-organization-id';
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
	private array $calendarResourceByCalendarIdCache = [];

	public function __construct(
		private readonly OrganizationFolderService $organizationFolderService,
		private readonly ResourceService $resourceService,
		private readonly AuthorizationService $authorizationService,
		private readonly IUserSession $userSession,
	) {
	}

	public function initialize(Server $server): void {
		$server->on('preloadCollection', $this->preloadCollection(...));

		// priority 90 ensures we get asked before the dav apps FilesPlugin, so we can reduce the permissions prop if necessary
		$server->on('propFind', $this->propFind(...), 90);
	}

	public function clearCaches(): void {
		$this->apiPermissionsScratchpad = [];
		$this->organizationFolderByNodeIdCache = [];
		$this->organizationFolderByIdCache = [];
		$this->folderResourceByNodeIdCache = [];
		$this->calendarResourceByCalendarIdCache = [];
	}

	private function getOrganizationFolderFromFolderNode(Folder $node): OrganizationFolder {
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

	private function getOrganizationFolderById(int $id) {
		if(isset($this->organizationFolderByIdCache[$id])) {
			return $this->organizationFolderByIdCache[$id];
		}

		return $this->organizationFolderByIdCache[$id] = $this->organizationFolderService->find($id);
	}

	private function getFolderResourceFromFolderNode(Folder $node): FolderResource {
		$nodeId = $node->getId();

		if(isset($this->folderResourceByNodeIdCache[$nodeId])) {
			return $this->folderResourceByNodeIdCache[$nodeId];
		}

		return $this->folderResourceByNodeIdCache[$nodeId] = $this->resourceService->findByFilesystemNode($node, true);
	}

	private function getCalendarResourceFromCalendarNode(Calendar $node): CalendarResource {
		$calendarId = $node->getResourceId();

		return $this->calendarResourceByCalendarIdCache[$calendarId] = $this->resourceService->findByCalendarId($calendarId);
	}

	private function getFolderLevel(string $internalPath): int {
		return  count(array_filter(
			array: explode('/', $internalPath),
			callback: fn($part) => $part !== ''
		));
	}

	private function preloadCollection(PropFind $propFind, ICollection $collection): void {
		if(!($collection instanceof Directory)) {
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
			$organizationFolder = $this->getOrganizationFolderFromFolderNode($node);
		} catch(\Exception $e) {
			return;
		}

		try {
			$resource = $this->getFolderResourceFromFolderNode($node);
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
		if ($sabreNode instanceof Directory) {
			$node = $sabreNode->getNode();

			if(!$node instanceof Folder) {
				return;
			}

			$fileInfo = $sabreNode->getFileInfo();
			$mount = $fileInfo->getMountPoint();

			if (!$mount instanceof GroupMountPoint) {
				return;
			}

			$this->propFindFolder($propFind, $node, $mount);
		} else if(($sabreNode instanceof Calendar)) {
			$this->propFindCalendar($propFind, $sabreNode);
		}
	}

	/**
	 * @todo use prop handler helper methods like propFindCalendar
	 */
	private function propFindFolder(PropFind $propFind, Folder $node, GroupMountPoint $mount): void {
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
		 * @var ?FolderResource
		 */
		$resource = null;

		$propFind->handle(self::ORGANIZATION_FOLDER_ID_PROPERTYNAME, function () use (&$node, &$isInOrganizationFolder, &$organizationFolder): ?int {
			try {
				if(!isset($organizationFolder)) {
					$organizationFolder = $this->getOrganizationFolderFromFolderNode($node);
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
					$organizationFolder = $this->getOrganizationFolderFromFolderNode($node);
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
					$organizationFolder = $this->getOrganizationFolderFromFolderNode($node);
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
					$organizationFolder = $this->getOrganizationFolderFromFolderNode($node);
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
					$resource = $this->getFolderResourceFromFolderNode($node);
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
					$resource = $this->getFolderResourceFromFolderNode($node);
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
					$resource = $this->getFolderResourceFromFolderNode($node);
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
					$resource = $this->getFolderResourceFromFolderNode($node);
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

	private function handlePropIfIsCalendarResource(PropFind $propFind, string $prop, Calendar $node, ?bool &$isResource, ?CalendarResource &$resource, \Closure $callback): void {
		$propFind->handle($prop, function () use ($node, &$isResource, &$resource, $callback) {
			if($isResource === false) {
				return null;
			}

			if(!isset($resource)) {
				try {
					$resource = $this->getCalendarResourceFromCalendarNode($node);
					$isResource = true;
				} catch (\Exception $e) {
					$isResource = false;

					return null;
				}
			}

			try {
				return $callback($resource);
			} catch (\Exception $e) {
				return null;
			}
		});
	}

	private function handlePropIfIsCalendarResourceIncludeOrganizationFolder(PropFind $propFind, string $prop, Calendar $node, ?bool &$isResource, ?CalendarResource &$resource, ?OrganizationFolder &$organizationFolder, \Closure $callback): void {
		$propFind->handle($prop, function () use ($node, &$isResource, &$resource, &$organizationFolder, $callback) {
			if($isResource === false) {
				return null;
			}

			if($resource === null) {
				try {
					$resource = $this->getCalendarResourceFromCalendarNode($node);
					$isResource = true;
				} catch (\Exception $e) {
					$isResource = false;

					return null;
				}
			}

			if($organizationFolder === null) {
				try {
					$organizationFolder = $this->getOrganizationFolderById($resource->getOrganizationFolderId());
				} catch (\Exception $e) {
					return null;
				}
			}
			
			try {
				return $callback($resource, $organizationFolder);
			} catch (\Exception $e) {
				return null;
			}
		});
	}

	private function propFindCalendar(PropFind $propFind, Calendar $node): void {
		/**
		 * @var ?bool
		 */
		$isResource = null;

		/**
		 * @var ?OrganizationFolder
		 */
		$organizationFolder = null;

		/**
		 * @var ?CalendarResource
		 */
		$resource = null;

		$this->handlePropIfIsCalendarResource(
			$propFind,
			self::ORGANIZATION_FOLDER_ID_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			fn (CalendarResource $resource): int =>
				$resource->getOrganizationFolderId(),
		);

		$this->handlePropIfIsCalendarResource(
			$propFind,
			self::ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			fn (CalendarResource $resource): int =>
				$resource->getId(),
		);

		$this->handlePropIfIsCalendarResource(
			$propFind,
			self::ORGANIZATION_FOLDER_RESOURCE_READ_LIMITED_PERMISSIONS_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			fn (CalendarResource $resource): string =>
				$this->authorizationService->isGranted($resource, "READ_LIMITED", $this->apiPermissionsScratchpad) ? 'true' : 'false',
		);

		$this->handlePropIfIsCalendarResource(
			$propFind,
			self::ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			fn (CalendarResource $resource): string =>
				$this->authorizationService->isGranted($resource, "UPDATE", $this->apiPermissionsScratchpad) ? 'true' : 'false',
		);

		$this->handlePropIfIsCalendarResourceIncludeOrganizationFolder(
			$propFind,
			self::ORGANIZATION_FOLDER_ORGANIZATION_PROVIDER_ID_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			$organizationFolder,
			fn (CalendarResource $resource, OrganizationFolder $organizationFolder): ?string =>
				$organizationFolder->getOrganizationProviderId(),
		);

		$this->handlePropIfIsCalendarResourceIncludeOrganizationFolder(
			$propFind,
			self::ORGANIZATION_FOLDER_ORGANIZATION_ID_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			$organizationFolder,
			fn (CalendarResource $resource, OrganizationFolder $organizationFolder): ?int =>
				$organizationFolder->getOrganizationId(),
		);

		$this->handlePropIfIsCalendarResourceIncludeOrganizationFolder(
			$propFind,
			self::ORGANIZATION_FOLDER_READ_PERMISSIONS_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			$organizationFolder,
			fn (CalendarResource $resource, OrganizationFolder $organizationFolder): string =>
				$this->authorizationService->isGranted($organizationFolder, "READ", $this->apiPermissionsScratchpad) ? 'true' : 'false',
		);

		$this->handlePropIfIsCalendarResourceIncludeOrganizationFolder(
			$propFind,
			self::ORGANIZATION_FOLDER_READ_LIMITED_PERMISSIONS_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			$organizationFolder,
			fn (CalendarResource $resource, OrganizationFolder $organizationFolder): string =>
				$this->authorizationService->isGranted($organizationFolder, "READ_LIMITED", $this->apiPermissionsScratchpad) ? 'true' : 'false',
		);

		$this->handlePropIfIsCalendarResourceIncludeOrganizationFolder(
			$propFind,
			self::ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME,
			$node,
			$isResource,
			$resource,
			$organizationFolder,
			fn (CalendarResource $resource, OrganizationFolder $organizationFolder): string =>
				$this->authorizationService->isGranted($organizationFolder, "UPDATE", $this->apiPermissionsScratchpad) ? 'true' : 'false',
		);

		// if no permission to view link share then set to [] before PublishPlugin
		// if not logged in this is the public view and the client already knows the publish-url
		$this->handlePropIfIsCalendarResource(
			$propFind,
			'{' . PublishPlugin::NS_CALENDARSERVER . '}publish-url',
			$node,
			$isResource,
			$resource,
			fn (CalendarResource $resource) => (!$this->userSession->isLoggedIn() || $this->authorizationService->isGranted($resource, "READ_LINK_SHARES", $this->apiPermissionsScratchpad)) ? null : [],
		);
	}
}