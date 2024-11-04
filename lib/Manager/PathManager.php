<?php

namespace OCA\OrganizationFolders\Manager;

use OCP\IConfig;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Folder;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;

use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Model\OrganizationFolder;

class PathManager {
	public function __construct(
		private IConfig $config,
		private IRootFolder $rootFolder,
		private FolderManager $groupfolderFolderManager,
		private MountProvider $mountProvider,
	){}

	private function getRootFolderStorageId(): ?int {
		return $this->rootFolder->getMountPoint()->getNumericStorageId();
	}

	public function getOrganizationFolderNode(OrganizationFolder $organizationFolder): ?Folder {
		return $this->getOrganizationFolderNodeById($organizationFolder->getId());
	}
	/** Get underlying groupfolder folder node for the organization folder
	 * (or if it was never before used create it in the filesystem and filecache!)
	*/
	public function getOrganizationFolderNodeById(int $id)/*: ?Folder*/ {
		return $this->mountProvider->getFolder(id: $id, create: True);
	}

	public function getOrganizationFolderSubfolder(OrganizationFolder $organizationFolder, array $path) {
		return $this->getOrganizationFolderByIdSubfolder($organizationFolder->getId(), $path);
	}

	public function getOrganizationFolderByIdSubfolder(int $id, array $path): ?Folder {
		$organizationFolderNode = $this->getOrganizationFolderNodeById($id);
		$currentFolder = $organizationFolderNode;

		foreach($path as $subfolder) {
			try {
				$currentFolder = $currentFolder->get($subfolder);
			} catch (\OCP\Files\NotFoundException $e) {
				return null;
			}
		}

		return $currentFolder;
	}
}