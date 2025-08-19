<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Dav;

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;

use OCP\Files\Folder;
use OCP\Files\DavUtil;

use OCA\DAV\Connector\Sabre\Node;
use OCA\DAV\Connector\Sabre\FilesPlugin;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupMountPoint;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Security\AuthorizationService;

class PropFindPlugin extends ServerPlugin {
	public const ORGANIZATION_FOLDER_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-id';
	public const ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-resource-id';
	public const ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-user-has-update-permissions';
	public const ORGANIZATION_FOLDER_READ_LIMITED_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-user-has-read-limited-permissions';
	public const ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-resource-user-has-update-permissions';

	public function __construct(
		private FolderManager $folderManager,
		private OrganizationFolderService $organizationFolderService,
		private ResourceService $resourceService,
		private AuthorizationService $authorizationService,
	) {
	}

	public function initialize(Server $server): void {
		// priority 90 ensures we get asked before the dav apps FilesPlugin, so we can reduce the permissions if necessary
		$server->on('propFind', $this->propFind(...), 90);
	}

	public function propFind(PropFind $propFind, INode $sabreNode): void {
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

		$folderLevel = count(array_filter(
			array: explode('/', $internalPath),
			callback: fn($part) => $part !== ''
		));

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

		$userHasOrganizationFolderUpdatePermissions = null;

		$propFind->handle(self::ORGANIZATION_FOLDER_ID_PROPERTYNAME, function () use (&$node, &$fileInfo, &$isInOrganizationFolder, &$organizationFolder): ?int {
			try {
				if(!isset($organizationFolder)) {
					$organizationFolder = $this->organizationFolderService->findByFilesystemNode($node);
				}

				$isInOrganizationFolder = true;
			} catch (\Exception $e) {
				$isInOrganizationFolder = false;

				return null;
			}

			return $organizationFolder->getId();
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME, function () use (&$node, &$fileInfo, $folderLevel, &$isInOrganizationFolder, &$organizationFolder, &$userHasOrganizationFolderUpdatePermissions): ?string {
			if($folderLevel > 0) {
				return null;
			}

			if($isInOrganizationFolder === false) {
				return null;
			}

			if(!isset($organizationFolder)) {
				try {
					$organizationFolder = $this->organizationFolderService->findByFilesystemNode($node);
					$isInOrganizationFolder = true;
				} catch (\Exception $e) {
					$isInOrganizationFolder = false;

					return null;
				}
			}

			try {
				$userHasOrganizationFolderUpdatePermissions = $this->authorizationService->isGranted(["UPDATE"], $organizationFolder);

				return $userHasOrganizationFolderUpdatePermissions ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_READ_LIMITED_PERMISSIONS_PROPERTYNAME, function () use (&$node, &$fileInfo, $folderLevel, &$isInOrganizationFolder, &$organizationFolder, &$userHasOrganizationFolderUpdatePermissions): ?string {
			if($folderLevel > 0) {
				return null;
			}

			if($isInOrganizationFolder === false) {
				return null;
			}

			// use cannot have update permissions and read only permissions at the same time, skip expensive READ_LIMITED check
			if(isset($userHasOrganizationFolderUpdatePermissions) && $userHasOrganizationFolderUpdatePermissions) {
				return 'false';
			}

			if(!isset($organizationFolder)) {
				try {
					$organizationFolder = $this->organizationFolderService->findByFilesystemNode($node);
					$isInOrganizationFolder = true;
				} catch (\Exception $e) {
					$isInOrganizationFolder = false;

					return null;
				}
			}

			try {
				return $this->authorizationService->isGranted(["READ_LIMITED"], $organizationFolder) ? 'true' : 'false';
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
					$resource = $this->resourceService->findByFilesystemNode($node, true);
					$isInOrganizationFolder = true;
					$isResource = true;
				} catch (\Exception $e) {
					$isResource = false;

					return null;
				}
			}

			return $resource->getId();
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
					$resource = $this->resourceService->findByFilesystemNode($node, true);
					$isInOrganizationFolder = true;
					$isResource = true;
				} catch (\Exception $e) {
					$isResource = false;

					return null;
				}
			}

			try {
				return $this->authorizationService->isGranted(["UPDATE"], $resource) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(FilesPlugin::PERMISSIONS_PROPERTYNAME, function () use ($node, &$isInOrganizationFolder, &$isResource, &$resource): string {
			if(!isset($resource)) {
				try {
					$resource = $this->resourceService->findByFilesystemNode($node, true);
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