<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Dav;

use Psr\Log\LoggerInterface;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

use OCP\Files\Folder;
use OCP\Files\NotFoundException;

use OCA\DAV\Connector\Sabre\Node;
use OCA\Files_Trashbin\Sabre\AbstractTrash;
use OCA\GroupFolders\Mount\GroupMountPoint;
use OCA\GroupFolders\Trash\GroupTrashItem;

use OCA\OrganizationFolders\Service\ResourceService;
use OCA\OrganizationFolders\Service\OrganizationFolderService;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Errors\Api\ResourceNotFound;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Errors\CannotDeleteResourceInFilesystem;
use OCA\OrganizationFolders\Errors\CannotCreateFileInRootOfOrganizationFolder;
use OCA\OrganizationFolders\Errors\CannotMoveFileOutOfOrganizationFolder;

/** Plugin applies extra permission restrictions on organization folders (and improves restoring files from the trashbin) */
class PermissionsPlugin extends ServerPlugin {
	/** @var Server */
	protected $server;

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OrganizationFolderService $organizationFolderService,
		private readonly ResourceService $resourceService,
		private readonly PathManager $pathManager,
	) {
	}

	public function initialize(Server $server) {
		$this->server = $server;

		$this->server->on('beforeUnbind', $this->beforeUnbind(...));
		$this->server->on('beforeMove', $this->beforeMove(...));
		$this->server->on('beforeBind', $this->beforeBind(...));
		
	}

	/**
	 * Deny deleting and moving of folder resources
	 *
	 * @param string $path
	 */
	public function beforeUnbind($path): bool {
		$sabreNode = $this->server->tree->getNodeForPath($path);

		if (!$sabreNode instanceof Node) {
			return true;
		}

		$node = $sabreNode->getNode();

		if (!$node instanceof Folder) {
			return true;
		}

		try {
			$this->resourceService->findByFilesystemNode($node);

			$this->logger->warning(
				"Prevented deletion of folder at path $path",
				['app' => 'organization_folders']
			);

			throw new CannotDeleteResourceInFilesystem($path);
		} catch (ResourceNotFound $e) {
			return true;
		}
	}


	/**
	 * Deny restoring nodes when gliederungs folder has been removed
	 * Deny moving nodes outside the group folder storage
	 *
	 * @param string $source
	 * @param string $destination
	 * @return void
	 */
	public function beforeMove($source, $destination): bool {
		$sourceSabreNode = $this->server->tree->getNodeForPath($source);

		if (!$sourceSabreNode instanceof AbstractTrash && !$sourceSabreNode instanceof Node) {
			return true;
		}

		$sourceFileInfo = $sourceSabreNode->getFileInfo();

		if ($sourceFileInfo instanceof GroupTrashItem) {
			// file getting restored from trash

			/** @var GroupMountPoint $mount */
			$sourceMount = $sourceFileInfo->getMountPoint();

			$groupFolderId = $sourceMount->getFolderId();

			try {
				$organizationFolder = $this->organizationFolderService->find($groupFolderId);
			} catch (OrganizationFolderNotFound $e) {
				// groupfolder, but not organization folder
				return true;
			}

			$relativePath = $sourceFileInfo->getInternalOriginalLocation();
			$relativePathParts = array_filter(explode('/', trim($relativePath, '/')));

			if(count($relativePathParts) < 2) {
				// tried to restore to root of organzation folder, where only resources can exist
				throw new CannotCreateFileInRootOfOrganizationFolder();
			}

			// While the parent folders of the destination path can be created for it to be restored into
			// folder resources should not be created, so at the least the first directory needs to be a valid resource.
			// TODO: once other resource types are implemented we need to check the whole path for name conflicts
			try {
				$resource = $this->resourceService->findByName(
					organizationFolderId: $organizationFolder->getId(),
					parentResourceId: null,
					name: $relativePathParts[0],
				);
			} catch (ResourceNotFound $e) {
				// trying to restore to a resource, that no longer exists
				return false;
			}

			if($resource->getType() !== "folder") {
				return false;
			}

			return $this->restoreAncestorPath($organizationFolder, $relativePath);
		} elseif ($sourceFileInfo instanceof \OC\Files\FileInfo) {
			$sourceMount = $sourceFileInfo->getMountPoint();

			if (!$sourceMount instanceof GroupMountPoint) {
				// ignore if the source file is outside of group folder app
				return true;
			}

			try {
				$destinationNode = $this->server->tree->getNodeForPath($destination);
			} catch (\Sabre\DAV\Exception\NotFound $e) {
				// Attempting to fetch its parent
				list($parentName, ) = \Sabre\Uri\split($destination);
				$destinationNode = $this->server->tree->getNodeForPath($parentName);
			}

			if (!$destinationNode instanceof Node) {
				// neither destination nor destination parent folder exist
				return false;
			}

			$destinationInfo = $destinationNode->getFileInfo();
			$destinationMount = $destinationInfo->getMountPoint();

			if (!$destinationMount instanceof GroupMountPoint) {
				// deny if destination is outside of group folder app
				throw new CannotMoveFileOutOfOrganizationFolder();
			}

			return $this->beforeBind($destination);
		}

		return true;
	}


	/**
	 * Restores ancestor folders if they dont exist
	 *
	 * @param Folder $organizationFolderNode
	 * @param string $relativePath
	 */
	public function restoreAncestorPath(OrganizationFolder $organizationFolder, string $relativePath): bool {
		list($parent, $filename) = \Sabre\Uri\split($relativePath);
		$parentPath = explode('/', $parent);

		$folder = $this->pathManager->getOrganizationFolderNode($organizationFolder);

		$log = function($message) use ($organizationFolder, $relativePath) {
			$this->logger->warning(
				message: "Ancestore restore for "
				. " organization folder " . $organizationFolder->getId() . ", "
				. "relativePath: \"" . $relativePath . "\": "
				. $message,
				context: ['app' => 'organization_folders']
			);
		};

		while (true) {
			if (count($parentPath) === 0) {
				try {
					# only allow restoring if there is no file with the same name at original location
					$folder->get($filename);
					$log("ERROR: file name already exists at original location");
					return false;
				} catch (NotFoundException $e) {
					$log("SUCCESS");
					return true;
				}
			}
			$directoryName = array_shift($parentPath);
			try {
				$folder = $folder->get($directoryName);
				//$log("PROGRESS: directory \"" . $directoryName . "\" already exists");
			} catch (NotFoundException $e) {
				/** @var Folder $folder */
				$folder = $folder->newFolder($directoryName);
				$log("PROGRESS: directory \"" . $directoryName . "\" successfully created");
			}
		}
	}


	/**
	 * Deny creating files in the organization folder root space
	 * where only resources may exist
	 *
	 * @param string $target
	 */
	public function beforeBind($target): bool {
		[$parentPath, ] = \Sabre\Uri\split($target);

		$parentSabreNode = $this->server->tree->getNodeForPath($parentPath);

		if (!$parentSabreNode instanceof Node) {
			return true;
		}

		$parentNode = $parentSabreNode->getNode();

		try {
			$this->organizationFolderService->findByFilesystemNode($parentNode);
		} catch (OrganizationFolderNotFound $e) {
			// either not even a groupfolder or a groupfolder that is not an organization folder
			return true;
		}

		$mount = $parentNode->getMountPoint();

		$relativePath = $mount->getInternalPath($parentNode->getPath());
		$relativePathParts = array_filter(explode('/', trim($relativePath, '/')));

		// accept, if parent of new file or folder is at least one directory level deep in organization folder
		if(count($relativePathParts) >= 1) {
			return true;
		} else {
			$this->logger->warning(
				"Prevented creation of file or folder at path $target",
				['app' => 'organization_folders']
			);

			throw new CannotCreateFileInRootOfOrganizationFolder();
		}
	}
}
