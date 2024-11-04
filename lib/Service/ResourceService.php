<?php

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\GroupFolders\ACL\UserMapping\UserMappingManager;
use OCA\GroupFolders\ACL\Rule;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\ResourceMapper;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Errors\InvalidResourceType;
use OCA\OrganizationFolders\Errors\ResourceNotFound;
use OCA\OrganizationFolders\Errors\ResourceNameNotUnique;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\Manager\ACLManager;

class ResourceService {
	public function __construct(
		private ResourceMapper $mapper,
		private PathManager $pathManager,
		protected ACLManager $aclManager,
		private UserMappingManager $userMappingManager,
	) {
	}

	public function findAll(int $organizationFolderId, int $parentResourceId = null, array $filters = []) {
		return $this->mapper->findAll($organizationFolderId, $parentResourceId, $filters);
	}

	private function handleException(Exception $e, int $id): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new ResourceNotFound($id);
		} else {
			throw $e;
		}
	}

	public function find(int $id): Resource {
		try {
			return $this->mapper->find($id);
		} catch (Exception $e) {
			$this->handleException($e, $id);
		}
	}

	/* Use named arguments to call this function */
	public function create(
		string $type,
		int $organizationFolderId,
		string $name,
		?int $parentResourceId = null,
		bool $active = true,

		?int $membersAclPermission = null,
		?int $managersAclPermission = null,
		?int $inheritedAclPermission = null,
	) {
		if($type === "folder") {
			$resource = new FolderResource();
		}  else {
			throw new InvalidResourceType($type);
		}

		if(!$this->mapper->existsWithName($organizationFolderId, $parentResourceId, $name)) {
			$resource->setOrganizationFolderId($organizationFolderId);
			$resource->setName($name);
			$resource->setActive($active);
			$resource->setLastUpdatedTimestamp(time());

			if(isset($parentResourceId)) {
				$parentResource = $this->find($parentResourceId);

				$resource->setParentResource($parentResource->getId());

				$parentNode = $this->pathManager->getFolderResourceNode($parentResource);
			} else {
				$parentNode = $this->pathManager->getOrganizationFolderNodeById($organizationFolderId);
			}

			if($type === "folder") {
				$resourceNode = $parentNode->newFolder($name);
				$fileId = $resourceNode->getId();

				if($fileId === -1) {
					throw new Exception("Unknown error occured while creating resource folder");
				}

				if(isset($membersAclPermission, $managersAclPermission, $inheritedAclPermission)) {
					$resource->setMembersAclPermission($membersAclPermission);
					$resource->setManagersAclPermission($managersAclPermission);
					$resource->setInheritedAclPermission($inheritedAclPermission);
					$resource->setFileId($fileId);
				} else {
					throw new \InvalidArgumentException("Folder specific parameters must be included, when creating a resource of type folder");
				}
			}

			$resource = $this->mapper->insert($resource);

			return $resource;
		} else {
			throw new ResourceNameNotUnique();
		}
	}

	/* Use named arguments to call this function */
	public function update(
			int $id,

			?string $name = null,
			?int $parentResource = null,
			?bool $active = null,

			?int $membersAclPermission = null,
			?int $managersAclPermission = null,
			?int $inheritedAclPermission = null,
		): Resource {
		$resource = $this->find($id);

		if(isset($parentResource)) {
			$resource->setParentResource($parentResource);
		}

		if(isset($name)) {
			if($this->mapper->existsWithName($resource->getGroupFolderId(), $resource->getParentResource(), $name)) {
				throw new ResourceNameNotUnique();
			} else {
				$resource->setName($name);
			}
		}

		if(isset($active)) {
			$resource->setActive($active);
		}

		if($resource->getType() === "folder") {
			if(isset($membersAclPermission)) {
				$resource->setMembersAclPermission($membersAclPermission);
			}

			if(isset($managersAclPermission)) {
				$resource->setManagersAclPermission($managersAclPermission);
			}

			if(isset($inheritedAclPermission)) {
				$resource->setInheritedAclPermission($inheritedAclPermission);
			}
		}  else {
			throw new InvalidResourceType($resource->getType());
		}

		if(count($resource->getUpdatedFields()) > 0) {
			$resource->setLastUpdatedTimestamp(time());
		}

		return $this->mapper->update($resource);
	}

	public function setAllFolderResourceAclsInOrganizationFolder(OrganizationFolder $organizationFolder, array $inheritingGroups) {
        $topLevelFolderResources = $this->findAll($organizationFolder->getId(), null, ["type" => "folder"]);
		
		return $this->recursivelySetFolderResourceALCs($topLevelFolderResources, "", $inheritingGroups);
    }

	/**
	 * Recursively overwrite ACL rules for an array of folder resources
	 *
	 * @param array $folderResources
	 * @psalm-param FolderResource[] $folderResources
	 * @param string $path
	 * @psalm-param string $path
	 * @param array $inheritingGroups
	 * @psalm-param string[] $inheritingGroups
	 */
	public function recursivelySetFolderResourceALCs(array $folderResources, string $path, array $inheritingGroups) {
		foreach($folderResources as $folderResource) {
			$resourceFileId = $folderResource->getFileId();
			$acls = [];

			foreach($inheritingGroups as $inheritingGroup) {
				$acls[] = new Rule(userMapping: $this->userMappingManager->mappingFromId("group", $inheritingGroup),
					fileId: $resourceFileId,
					mask: 31,
					permissions: $folderResource->getInheritedAclPermission(),
				);
			}

			$this->aclManager->overwriteACLsForFileId($resourceFileId, $acls);

			// TODO: recurse sub-resources
		}
	}

	public function delete(int $id): Resource {
		try {
			$resource = $this->mapper->find($id);
			$this->mapper->delete($resource);
			return $resource;
		} catch (Exception $e) {
			$this->handleException($e, $id);
		}
	}
}
