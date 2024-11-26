<?php

namespace OCA\OrganizationFolders\Service;

use Exception;

use Psr\Container\ContainerInterface;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\GroupFolders\ACL\UserMapping\UserMappingManager;
use OCA\GroupFolders\ACL\Rule;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\ResourceMapper;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use \OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
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
		protected OrganizationProviderManager $organizationProviderManager,
		protected OrganizationFolderService $organizationFolderService,
		protected ContainerInterface $container,
		protected PrincipalFactory $principalFactory,
	) {
	}

	/**
	 * @param int $organizationFolderId
	 * @psalm-param int $organizationFolderId
	 * @param int|null $parentResourceId
	 * @psalm-param int|null $parentResourceId
	 * @param array $filters
	 * @psalm-param array $filters
	 * @psalm-return Resource[]
	 */
	public function findAll(int $organizationFolderId, ?int $parentResourceId = null, array $filters = []): array {
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

	public function findByFileId(int $fileId): FolderResource {
		// TODO: improve error handling
		return $this->mapper->findByFileId($fileId);
	}

	/* Use named arguments to call this function */
	public function create(
		string $type,
		int $organizationFolderId,
		string $name,
		?int $parentResourceId = null,
		bool $active = true,
		bool $inheritManagers = true,

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
			$resource->setInheritManagers($inheritManagers);
			$resource->setLastUpdatedTimestamp(time());

			if(isset($parentResourceId)) {
				$parentResource = $this->find($parentResourceId);

				if($parentResource->getOrganizationFolderId() === $organizationFolderId) {
					if($parentResource->getType() !== "folder") {
						throw new Exception("Only folder resources can have sub-resources");
					} else {
						$resource->setParentResource($parentResource->getId());
					}
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

			$this->organizationFolderService->applyPermissions($organizationFolderId);

			return $resource;
		} else {
			throw new ResourceNameNotUnique();
		}
	}

	/* Use named arguments to call this function */
	public function update(
			int $id,

			?string $name = null,
			?bool $active = null,
			?bool $inheritManagers = null,

			?int $membersAclPermission = null,
			?int $managersAclPermission = null,
			?int $inheritedAclPermission = null,
		): Resource {
		$resource = $this->find($id);

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

		if(isset($inheritManagers)) {
			$resource->setInheritManagers($inheritManagers);
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

		$resource = $this->mapper->update($resource);

		$this->organizationFolderService->applyPermissions($resource->getOrganizationFolderId());

		return $resource;

		// TODO: improve error handing: if db update fails roll back changes in the filesystem
	}

	public function setAllFolderResourceAclsInOrganizationFolder(OrganizationFolder $organizationFolder, array $inheritingGroups) {
        $topLevelFolderResources = $this->findAll($organizationFolder->getId(), null, ["type" => "folder"]);
		
		$inheritingPrincipals = [];
		foreach($inheritingGroups as $inheritingGroup) {
			$inheritingPrincipals[] = $this->principalFactory->buildPrincipal(PrincipalType::GROUP, $inheritingGroup);
		}

		return $this->recursivelySetFolderResourceALCs($topLevelFolderResources, "", $inheritingPrincipals);
    }

	/**
	 * Recursively overwrite ACL rules for an array of folder resources
	 *
	 * @param array $folderResources
	 * @psalm-param FolderResource[] $folderResources
	 * @param string $path
	 * @psalm-param string $path
	 * @param array $inheritingPrincipals
	 * @psalm-param Principal[] $inheritingPrincipals
	 */
	public function recursivelySetFolderResourceALCs(array $folderResources, string $path, array $inheritingPrincipals) {
		foreach($folderResources as $folderResource) {
			$resourceFileId = $folderResource->getFileId();
			$acls = [];

			// inherit ACLs
			foreach($inheritingPrincipals as $inheritingPrincipal) {
				$newACL = $this->aclManager->createAclRuleForPrincipal(
					principal: $inheritingPrincipal,
					fileId: $resourceFileId,
					mask: 31,
					permissions: $folderResource->getInheritedAclPermission(),
				);

				// if mapping for principal could not be created, skip creating rule for it
				if(!is_null($newACL)) {
					$acls[] = $newACL;
				}
			}

			// inherited principals will affect resources further down, if they have any permissions at this level
			if($folderResource->getInheritedAclPermission() !== 0) {
				$nextInheritingPrincipals = $inheritingPrincipals;
			} else {
				$nextInheritingPrincipals = [];
			}

			// member ACLs
			/** @var ResourceService */
			$resourceMemberService = $this->container->get(ResourceMemberService::class);
			$resourceMembers = $resourceMemberService->findAll($folderResource->getId());

			foreach($resourceMembers as $resourceMember) {
				if($resourceMember->getPermissionLevel() === ResourceMemberPermissionLevel::MANAGER->value) {
					$resourceMemberPermissions = $folderResource->getManagersAclPermission();
				} else if($resourceMember->getPermissionLevel() === ResourceMemberPermissionLevel::MEMBER->value) {
					$resourceMemberPermissions = $folderResource->getMembersAclPermission();
				} else {
					throw new Exception("invalid resource member permission level");
				}

				if($resourceMemberPermissions !== 0) {
					$resourceMemberPrincipal = $resourceMember->getPrincipal();

					$newACL = $this->aclManager->createAclRuleForPrincipal(
						principal: $resourceMemberPrincipal,
						fileId: $resourceFileId,
						mask: 31,
						permissions: $resourceMemberPermissions,
					);

					// if mapping for principal could not be created, skip creating rule for it
					if(!is_null($newACL)) {
						$acls[] = $newACL;
						$nextInheritingPrincipals[] = $resourceMemberPrincipal;
					}
				}
			}

			$this->aclManager->overwriteACLsForFileId($resourceFileId, $acls);

			// recurse sub-resources
			$subFolderResources = $this->getSubResources($folderResource, ["type" => "folder"]);
			$this->recursivelySetFolderResourceALCs($subFolderResources, $path . $folderResource->getName() . "/", $nextInheritingPrincipals);
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
	/** 
	 * get all direct sub-resources
	 */
	public function getSubResources(Resource $resource, array $filters = []) {
		return $this->findAll($resource->getOrganizationFolderId(), $resource->getId(), $filters);
	}

	/**
	 * get all sub-resources recursively
	 */
	public function getAllSubResources(Resource $resource) {
		$subResources = $this->getSubResources($resource);

		foreach($subResources as $subResource) {
			$subResources = array_merge($subResources, $this->getAllSubResources($subResource));
		}

		return $subResources;
	}

	public function getParentResource(Resource $resource): ?Resource {
		if(!is_null($resource->getParentResource())) {
			return $this->find($resource->getParentResource());
		} else {
			return null;
		}
	}

	public function deleteById(int $id): Resource {
		try {
			$resource = $this->mapper->find($id);
			return $this->delete($resource);
		} catch (Exception $e) {
			$this->handleException($e, $resource->getId());
		}
	}

	public function delete(Resource $resource): Resource {
		// first delete all subresources recursively
		$subResources = $this->getSubResources($resource);
		
		foreach($subResources as $subResource) {
			$this->delete($subResource);
		}

		// delete in filesystem if type folder
		if($resource->getType() === "folder") {
			$this->getFolderResourceFilesystemNode($resource)->delete();
		}
		
		// delete in database
		$this->mapper->delete($resource);
		return $resource;
		
	}
}
