<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Dav;

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;

use OCA\DAV\Connector\Sabre\Node;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\GroupMountPoint;

use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Security\AuthorizationService;

class PropFindPlugin extends ServerPlugin {
	public const ORGANIZATION_FOLDER_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-id';
	public const ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-resource-id';
	public const ORGANIZATION_FOLDER_RESOURCE_MANAGER_PERMISSIONS_PROPERTYNAME = '{http://verdigado.com/ns}organization-folder-resource-user-has-manager-permissions';

	public function __construct(
		private FolderManager $folderManager,
		private ResourceService $resourceService,
		private AuthorizationService $authorizationService,
	) {
	}

	public function initialize(Server $server): void {
		$server->on('propFind', $this->propFind(...));
	}

	public function propFind(PropFind $propFind, INode $node): void {
		if (!$node instanceof Node) {
			return;
		}

		$fileInfo = $node->getFileInfo();
		$mount = $fileInfo->getMountPoint();

		if (!$mount instanceof GroupMountPoint) {
			return;
		}


		$propFind->handle(self::ORGANIZATION_FOLDER_ID_PROPERTYNAME, function () use ($fileInfo): int {
			return $this->folderManager->getFolderByPath($fileInfo->getPath());
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_RESOURCE_ID_PROPERTYNAME, function () use ($node): ?int {
			try {
				return $this->resourceService->findByFileId($node->getId())->getId();
			} catch (\Exception $e) {
				return null;
			}
		});

		$propFind->handle(self::ORGANIZATION_FOLDER_RESOURCE_MANAGER_PERMISSIONS_PROPERTYNAME, function () use ($node) {
			try {
				$resource = $this->resourceService->findByFileId($node->getId());
				return $this->authorizationService->isGranted(["READ"], $resource) ? 'true' : 'false';
			} catch (\Exception $e) {
				return null;
			}
		});
	}
}