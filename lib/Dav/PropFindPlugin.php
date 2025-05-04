<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Dav;

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;

use OCP\Files\Folder;

use OCA\DAV\Connector\Sabre\Node;
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
		$server->on('propFind', $this->propFind(...));
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

		/**
		 * @var ?OrganizationFolder
		 */
		$organizationFolder = null;

		/**
		 * @var ?Resource
		 */
		$resource = null;

		$userHasOrganizationFolderUpdatePermissions = null;

		$propFind->handle(self::ORGANIZATION_FOLDER_ID_PROPERTYNAME, function () use (&$node, &$fileInfo, &$organizationFolder): ?int {
			try {
				if(!isset($organizationFolder)) {
					$organizationFolder = $this->organizationFolderService->findByFilesystemNode($node);
				}

				return $organizationFolder->getId();
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_UPDATE_PERMISSIONS_PROPERTYNAME, function () use (&$node, &$fileInfo, &$organizationFolder, &$userHasOrganizationFolderUpdatePermissions): ?string {
			try {
				if(!isset($organizationFolder)) {
					$organizationFolder = $this->organizationFolderService->findByFilesystemNode($node);
				}

				$userHasOrganizationFolderUpdatePermissions = $this->authorizationService->isGranted(["UPDATE"], $organizationFolder);

				return $userHasOrganizationFolderUpdatePermissions ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_READ_LIMITED_PERMISSIONS_PROPERTYNAME, function () use (&$node, &$fileInfo, &$organizationFolder, &$userHasOrganizationFolderUpdatePermissions): ?string {
			try {
				// use cannot have update permissions and read only permissions at the same time, skip expensive READ_LIMITED check
				if(isset($userHasOrganizationFolderUpdatePermissions) && $userHasOrganizationFolderUpdatePermissions) {
					return 'false';
				}

				if(!isset($organizationFolder)) {
					$organizationFolder = $this->organizationFolderService->findByFilesystemNode($node);
				}

				return $this->authorizationService->isGranted(["READ_LIMITED"], $organizationFolder) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME, function () use ($node, &$resource): ?int {
			try {
				if(!isset($resource)) {
					$resource = $this->resourceService->findByFilesystemNode($node);
				}

				return $resource->getId();
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_RESOURCE_UPDATE_PERMISSIONS_PROPERTYNAME, function () use ($node, &$resource) {
			try {
				if(!isset($resource)) {
					$resource = $this->resourceService->findByFilesystemNode($node);
				}

				return $this->authorizationService->isGranted(["UPDATE"], $resource) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});
	}
}