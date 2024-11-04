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

	public function getOrganizationFolderNodeById(int $id): ?Folder {
		return $this->mountProvider->getFolder($id, False);
	}

	public function getFolderResourceNode(FolderResource $resource): ?Folder {
		$organizationFolderNode = $this->getOrganizationFolderNodeById($resource->getOrganizationFolderId());

		return $organizationFolderNode->getFirstNodeById($resource->getFileId());
	}
}