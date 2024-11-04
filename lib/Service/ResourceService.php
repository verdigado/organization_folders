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
use OCA\OrganizationFolders\Enum\MemberPermissionLevel;
use OCA\OrganizationFolders\Enum\MemberType;
use OCA\OrganizationFolders\Errors\InvalidResourceType;
use OCA\OrganizationFolders\Errors\ResourceNotFound;
use OCA\OrganizationFolders\Errors\ResourceNameNotUnique;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\Manager\ACLManager;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class ResourceService {
	public function __construct(
		protected ResourceMapper $mapper,
		protected PathManager $pathManager,
		protected ACLManager $aclManager,
		protected UserMappingManager $userMappingManager,
		protected ResourceMemberService $resourceMemberService,
		protected OrganizationProviderManager $organizationProviderManager,
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

				if($parentResource->getOrganizationFolderId() === $organizationFolderId) {
					$resource->setParentResource($parentResource->getId());
				} else {
					throw new Exception("Cannot create child-resource of parent in different organizationId");
				}

				$parentNode = $this->getFolderResourceFilesystemNode($parentResource);
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
			if($this->mapper->existsWithName($resource->getOrganizationFolderId(), $resource->getParentResource(), $name)) {
				throw new ResourceNameNotUnique();
			} else {
				if($resource->getType() === "folder") {
					$resourceNode = $this->getFolderResourceFilesystemNode($resource);
					$newPath = $resourceNode->getParent()->getPath() . "/" . $name;
					$resourceNode->move($newPath);
				}

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
		// TODO: improve error handing: if db update fails roll back changes in the filesystem
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

			// inherit ACLs
			foreach($inheritingGroups as $inheritingGroup) {
				$acls[] = new Rule(
					userMapping: $this->userMappingManager->mappingFromId("group", $inheritingGroup),
					fileId: $resourceFileId,
					mask: 31,
					permissions: $folderResource->getInheritedAclPermission(),
				);
			}

			// member ACLs
			$resourceMembers = $this->resourceMemberService->findAll($folderResource->getId());
			foreach($resourceMembers as $resourceMember) {
				if($resourceMember->getPermissionLevel() === MemberPermissionLevel::MANAGER->value) {
					$resourceMemberPermissions = $folderResource->getManagersAclPermission();
				} else if($resourceMember->getPermissionLevel() === MemberPermissionLevel::MEMBER->value) {
					$resourceMemberPermissions = $folderResource->getMembersAclPermission();
				} else {
					throw new Exception("invalid resource member permission level");
				}

				if($resourceMember->getType() === MemberType::USER->value) {
					$mapping = $this->userMappingManager->mappingFromId("user", $resourceMember->getPrincipal());
				} else if($resourceMember->getType() === MemberType::GROUP->value) {
					$mapping = $this->userMappingManager->mappingFromId("group", $resourceMember->getPrincipal());
				} else if($resourceMember->getType() === MemberType::ROLE->value) {
					[$organizationProviderId, $roleId] = explode(":", $resourceMember->getPrincipal(), 2);
					$organizationProvider = $this->organizationProviderManager->getOrganizationProvider($organizationProviderId);
					$role = $organizationProvider->getRole($roleId);
					$mapping = $this->userMappingManager->mappingFromId("group", $role->getMembersGroup());
				} else {
					throw new Exception("invalid resource member type");
				}

				if(is_null($mapping)) {
					// TODO: skip member instead of crashing
					throw new Exception(message: "invalid mapping, likely non-existing group");
				}
				
				$acls[] = new Rule(
					userMapping: $mapping,
					fileId: $resourceFileId,
					mask: 31,
					permissions: $resourceMemberPermissions,
				);
			}

			$this->aclManager->overwriteACLsForFileId($resourceFileId, $acls);

			// TODO: recurse sub-resources
		}
	}

	public function getResourcePath(FolderResource $resource) {
		$currentResource = $resource;
		
		$invertedPath = [];

		$invertedPath[] = $currentResource->getName();

		while($currentResource->getParentResource()) {
			$currentResource = $this->find($currentResource->getParentResource());
			$invertedPath[] = $currentResource->getName();
		}

		return array_reverse($invertedPath);
	}

	public function getFolderResourceFilesystemNode(FolderResource $resource) {
		return $this->pathManager->getOrganizationFolderByIdSubfolder($resource->getOrganizationFolderId(), $this->getResourcePath($resource));
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
