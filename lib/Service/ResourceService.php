<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Exception;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use OCP\IDBConnection;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\Files\Folder;

use OCA\GroupFolders\Mount\GroupMountPoint;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\ResourceMapper;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\PrincipalBackedByGroup;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Model\AclList;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Errors\InvalidResourceType;
use OCA\OrganizationFolders\Errors\InvalidResourceName;
use OCA\OrganizationFolders\Errors\ResourceNotFound;
use OCA\OrganizationFolders\Errors\ResourceNameNotUnique;
use OCA\OrganizationFolders\Errors\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\Manager\ACLManager;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Groups\GroupBackend;

class ResourceService {
	use TTransactional;

	public function __construct(
		protected readonly IDBConnection $db,
		protected readonly LoggerInterface $logger,
		protected readonly ResourceMapper $mapper,
		protected readonly PathManager $pathManager,
		protected readonly ACLManager $aclManager,
		protected readonly OrganizationProviderManager $organizationProviderManager,
		protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly ContainerInterface $container,
		protected readonly PrincipalFactory $principalFactory,
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

	private function handleException(Exception $e, array $criteria): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new ResourceNotFound($criteria);
		} else {
			throw $e;
		}
	}

	public function find(int $id): Resource {
		try {
			return $this->mapper->find($id);
		} catch (Exception $e) {
			$this->handleException($e, ["id" => $id]);
		}
	}

	public function findByFileId(int $fileId): FolderResource {
		try {
			return $this->mapper->findByFileId($fileId);
		} catch (Exception $e) {
			$this->handleException($e, ["fileId" => $fileId]);
		}
	}

	public function findByName(int $organizationFolderId, ?int $parentResourceId, string $name): Resource {
		try {
			return $this->mapper->findByName($organizationFolderId, $parentResourceId, $name);
		} catch (Exception $e) {
			$this->handleException($e, [
				"organizationFolderId" => $organizationFolderId,
				"parentResourceId" => $parentResourceId,
				"name" => $name,
			]);
		}
	}

	/**
	 * Find a resource within organization folder by it's path relative to the organization folder
	 */
	public function findByRelativePath(int $organizationFolderId, string $relativePath): Resource {
		$relativePathParts = explode('/', trim($relativePath, '/'));

		/** @var ?Resource */
		$subresource = null;

		try {
			for($i = 0; $i < (count($relativePathParts) - 1); $i++) {
				$subresource = $this->mapper->findByName($organizationFolderId, $subresource?->getId(), $relativePathParts[$i]);

				if($subresource->getType() !== "folder") {
					throw new ResourceNotFound([
						"organizationFolderId" => $organizationFolderId,
						"relativePath" => $relativePath,
					]);
				}
			}

			$subresource = $this->mapper->findByName($organizationFolderId, $subresource?->getId(), end($relativePathParts));
			
			return $subresource;
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new ResourceNotFound([
				"organizationFolderId" => $organizationFolderId,
				"relativePath" => $relativePath,
			]);
		}
	}

	/**
	 * Find a folder resource by it's node in the filesystem
	 * @param Folder $folder
	 * @throws ResourceNotFound
	 * @return FolderResource
	 */
	public function findByFilesystemNode(Folder $folder): FolderResource {
		$mount = $folder->getMountPoint();

		if (!$mount instanceof GroupMountPoint) {
			// ignore if the target file is not part of group folder storage
			throw new ResourceNotFound([
				"path" => $folder->getPath(),
			]);
		}

		try {
			return $this->mapper->findByFileId($folder->getId());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			// Either
			// - this Folder node is not a resource
			// - or the fileId of the filesystem folder of the resource has changed
			//   This should not happen, but could be caused by bugs in the core or other apps

			try {
				$organizationFolder = $this->organizationFolderService->findByFilesystemNode($folder);

				$relativeResourcePath = $mount->getInternalPath($folder->getPath());
			
				$resource = $this->findByRelativePath($organizationFolder->getId(), $relativeResourcePath);

				$this->logger->warning(
					"The resource "
					. json_encode($resource)
					. "was just found by it's path, but not by it's fileId. This should not happen, investigate the cause! Proceeding normally."
				);

				return $resource;
			} catch (OrganizationFolderNotFound|ResourceNotFound $e) {
				throw new ResourceNotFound([
					"path" => $folder->getPath(),
				]);
			}
		}
	}

	public function isValidResourceName(string $name): bool {
		return !preg_match('/[`$%^*={};"\\\\|<>\/?~]/', $name);
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

		// special mode only applicable if type = "folder", that uses an existing folder with the resource name 
		?bool $folderAlreadyExists = false,
		?bool $skipPermssionsApply = false,
	) {
		if($type === "folder") {
			$resource = new FolderResource();
		}  else {
			throw new InvalidResourceType($type);
		}

		if(!$this->isValidResourceName($name)) {
			throw new InvalidResourceName($name);
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
				if($folderAlreadyExists) {
					$resourceNode = $parentNode->get($name);

					if(!(isset($resourceNode) && $resourceNode instanceof Folder)) {
						throw new Exception("Resource folder does not exist or is a file, cannot proceed");
					}
				} else {
					if($parentNode->nodeExists($name)) {
						throw new Exception("A subfolder with this name already exists");
					}

					$resourceNode = $parentNode->newFolder($name);
				}
				
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

			try {
				$resource = $this->mapper->insert($resource);
			} catch (\Throwable $e) {
				// an error occured, rewind all changes, depending on resource type
				if($type === "folder" && !$folderAlreadyExists) {
					$resourceNode?->delete();
				}
				throw $e;
			}

			if(!$skipPermssionsApply) {
				$this->organizationFolderService->applyPermissionsById($organizationFolderId);
			}

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
			if(!$this->isValidResourceName($name)) {
				throw new InvalidResourceName($name);
			}

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

		$this->organizationFolderService->applyPermissionsById($resource->getOrganizationFolderId());

		return $resource;

		// TODO: improve error handing: if db update fails roll back changes in the filesystem
	}
	/**
	 * @param OrganizationFolder $organizationFolder
	 * @psalm-param PrincipalBackedByGroup[] $inheritedMemberPrincipals
	 * @psalm-param PrincipalBackedByGroup[] $inheritedManagerPrincipals
	 */
	public function setAllFolderResourceAclsInOrganizationFolder(
		OrganizationFolder $organizationFolder,
		array $memberPrincipals,
		array $managerPrincipals,
	): void {
        $topLevelFolderResources = $this->findAll($organizationFolder->getId(), null, ["type" => "folder"]);

		$this->recursivelySetFolderResourceALCs(
			folderResources: $topLevelFolderResources,
			path: "",
			inheritedMemberPrincipals: $memberPrincipals,
			inheritedManagerPrincipals: $managerPrincipals,
		);
    }

	/**
	 * Recursively overwrite ACL rules for an array of folder resources
	 *
	 * @param array $folderResources
	 * @psalm-param FolderResource[] $folderResources
	 * @param string $path
	 * @param array $inheritedMemberPrincipals
	 * @psalm-param Principal[] $inheritedMemberPrincipals
	 * @param array $inheritedManagerPrincipals
	 * @psalm-param Principal[] $inheritedManagerPrincipals
	 * @param bool $implicitlyDeactivated
	 */
	public function recursivelySetFolderResourceALCs(
		array $folderResources,
		string $path,
		array $inheritedMemberPrincipals,
		array $inheritedManagerPrincipals,
		bool $implicitlyDeactivated = false
	): void {
		foreach($folderResources as $folderResource) {
			$resourceFileId = $folderResource->getFileId();

			if($folderResource->getActive() && !$implicitlyDeactivated) {
				$resourceMembersAclPermission = $folderResource->getMembersAclPermission();
				$resourceManagersAclPermission = $folderResource->getManagersAclPermission();
				$resourceInheritedAclPermission = $folderResource->getInheritedAclPermission();
			} else {
				$resourceMembersAclPermission = 0;
				$resourceManagersAclPermission = 0;
				$resourceInheritedAclPermission = 0;
			}
			
			$acls = new AclList($resourceFileId);

			// add default deny
			$acls->addRule(
				userMapping: new UserMapping(type: "group", id: GroupBackend::EVERYONE_GROUP, displayName: null),
				mask: 31,
				permissions: 0,
			);

			// inherited Member ACLs
			foreach($inheritedMemberPrincipals as $inheritedMemberPrincipal) {
				$acls->addRule(
					userMapping: $this->aclManager->getMappingForPrincipal($inheritedMemberPrincipal),
					mask: 31,
					permissions: $resourceInheritedAclPermission,
				);
			}

			// inherited member principals will affect resources further down, if they have any permissions at this level
			if($resourceInheritedAclPermission !== 0) {
				$nextInheritedMemberPrincipals = $inheritedMemberPrincipals;
			} else {
				$nextInheritedMemberPrincipals = [];
			}

			// inherited Manager ACLs
			if($folderResource->getActive() && !$implicitlyDeactivated && $folderResource->getInheritManagers()) {
				$inheritedManagerAclPermission = $resourceManagersAclPermission;
				$nextInheritedManagerPrincipals = $inheritedManagerPrincipals;
			} else {
				$inheritedManagerAclPermission = 0;
				$nextInheritedManagerPrincipals = [];
			}

			foreach($inheritedManagerPrincipals as $inheritedManagerPrincipal) {
				$acls->addRule(
					userMapping: $this->aclManager->getMappingForPrincipal($inheritedManagerPrincipal),
					mask: 31,
					permissions: $inheritedManagerAclPermission,
				);
			}

			// member ACLs
			/** @var ResourceMemberService */
			$resourceMemberService = $this->container->get(ResourceMemberService::class);
			$resourceMembers = $resourceMemberService->findAll($folderResource->getId());

			foreach($resourceMembers as $resourceMember) {
				$resourceMemberPrincipal = $resourceMember->getPrincipal();

				if($resourceMember->getPermissionLevel() === ResourceMemberPermissionLevel::MANAGER->value) {
					$resourceMemberPermissions = $resourceManagersAclPermission;

				} else if($resourceMember->getPermissionLevel() === ResourceMemberPermissionLevel::MEMBER->value) {
					$resourceMemberPermissions = $resourceMembersAclPermission;
				} else {
					throw new Exception("invalid resource member permission level");
				}

				if($resourceMemberPermissions !== 0) {
					$acls->addRule(
						userMapping: $this->aclManager->getMappingForPrincipal($resourceMemberPrincipal),
						mask: 31,
						permissions: $resourceMemberPermissions,
					);

					// members will affect resources further down, if they have any permissions at this level

					// NOTE: members of type MANAGER will get added to both nextInheritedMemberPrincipals and nextInheritedManagerPrincipals,
					// because even if manager inheritance is disabled in a child resource if the have read permissions they qualify for resourceInheritedAclPermission permissions
					$nextInheritedMemberPrincipals[] = $resourceMemberPrincipal;
				}

				if($resourceMember->getPermissionLevel() === ResourceMemberPermissionLevel::MANAGER->value) {
					$nextInheritedManagerPrincipals[] = $resourceMemberPrincipal;
				}
			}

			$this->aclManager->overwriteACLsForFileId($resourceFileId, $acls->getRules());

			// recurse sub-resources
			$subFolderResources = $this->getSubResources($folderResource, ["type" => "folder"]);
			$this->recursivelySetFolderResourceALCs(
				folderResources: $subFolderResources,
				path: $path . $folderResource->getName() . "/",
				inheritedMemberPrincipals: $nextInheritedMemberPrincipals,
				inheritedManagerPrincipals: $nextInheritedManagerPrincipals,
				implicitlyDeactivated: (!$folderResource->getActive() || $implicitlyDeactivated)
			);
		}
	}

	public function getResourcePath(Resource $resource): array {
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

	public function getUnmanagedSubfolders(FolderResource $resource) {
		$subNodes = $this->getFolderResourceFilesystemNode($resource)->getDirectoryListing();
		
		$subDirectoryNames = [];

		foreach($subNodes as $subNode) {
			if($subNode instanceof Folder) {
				$subDirectoryNames[] = $subNode->getName();
			}
		}

		$subResourceNames = $this->mapper->findAllNames($resource->getOrganizationFolderId(), $resource->getId(), [
			"type" => "folder",
		]);

		return array_values(array_diff($subDirectoryNames, $subResourceNames));
	}

	public function promoteUnmanagedSubfolder(FolderResource $resource, string $unmanagedSubfolderName) {
		if(strlen("unmanagedSubfolderName") == 0) {
			throw new Exception("Subfolder does not exist");
		}

		if(str_contains($unmanagedSubfolderName, "/")) {
			throw new Exception("You can only promote direct subfolders");
		}

		$parentResourceNode = $this->getFolderResourceFilesystemNode($resource);

		try {
			$unmanagedSubfolderNode = $parentResourceNode->get($unmanagedSubfolderName);
		} catch (\OCP\Files\NotFoundException $e) {
			throw new Exception("Subfolder does not exist");
		}

		// extra protection against escaping parent folder
		if(!$parentResourceNode->isSubNode($unmanagedSubfolderNode)) {
			throw new Exception("Subfolder does not exist");
		}

		if(!($unmanagedSubfolderNode instanceof Folder)) {
			throw new Exception("You can only promote folders, not files");
		}

		return $this->create(
			type: "folder",
			organizationFolderId: $resource->getOrganizationFolderId(),
			name: $unmanagedSubfolderName,
			parentResourceId: $resource->getId(),
			// match current permissions in subfolder
			inheritedAclPermission: $resource->getMembersAclPermission(), // Members in parent resource will be inherited members in new resource
			membersAclPermission: $resource->getMembersAclPermission(),
			managersAclPermission: $resource->getManagersAclPermission(),
			folderAlreadyExists: true,
		);
	}

	public function deleteById(int $id): Resource {
		try {
			$resource = $this->mapper->find($id);
			return $this->delete($resource);
		} catch (Exception $e) {
			$this->handleException($e, ["id" => $id]);
		}
	}

	public function delete(Resource $resource): Resource {
		return $this->atomic(function () use ($resource): Resource {
			// first delete all subresources recursively
			$subResources = $this->getSubResources($resource);
			
			foreach($subResources as $subResource) {
				$this->delete($subResource);
			}

			// delete in filesystem if type folder
			if($resource->getType() === "folder") {
				$node = $this->getFolderResourceFilesystemNode($resource);
				
				if(isset($node)) {
					$node->delete();
				} else {
					$this->logger->warning(
						"Tried deleting filesystem node of resource "
						. json_encode($resource)
						. ", but it did not exist. This should not happen, investigate the cause! Proceeding normally."
					);
				}
			}
			
			// delete in database
			$this->mapper->delete($resource);
			return $resource;
		}, $this->db);
	}
}
