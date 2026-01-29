<?php

namespace OCA\OrganizationFolders\Manager;

use OCP\Constants;
use OCP\IConfig;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\Mount\IMountManager;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCA\GroupFolders\Folder\FolderDefinitionWithPermissions;

use OCA\OrganizationFolders\Model\OrganizationFolder;

class PathManager {
	public function __construct(
		private IConfig $config,
		private IRootFolder $rootFolder,
		private FolderManager $groupfolderFolderManager,
		private MountProvider $mountProvider,
		private IMountManager $mountManager,
	){}

	public function getOrganizationFolderNode(OrganizationFolder $organizationFolder): ?Folder {
		return $this->getOrganizationFolderNodeById($organizationFolder->getId());
	}

	/** 
	 * Get underlying groupfolder folder node for the organization folder
	 * @TODO Expose node only to a given closure and unmount afterwards
	*/
	public function getOrganizationFolderNodeById(int $id): Folder {
		$folder = $this->groupfolderFolderManager->getFolder($id);

		$folderWithPermissions = FolderDefinitionWithPermissions::fromFolder($folder, $folder->rootCacheEntry, Constants::PERMISSION_ALL);

		$mountPoint = '/dummy/organization_folders/groupfolders/' . $folder->id;
		$mount = $this->mountProvider->getMount(folder: $folderWithPermissions, mountPoint: $mountPoint);

		$this->mountManager->addMount($mount);

		return $this->rootFolder->get($mountPoint);
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