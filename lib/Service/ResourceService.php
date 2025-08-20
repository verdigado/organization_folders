<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Exception;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use OCP\IDBConnection;
use OCP\IL10N;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\Files\Folder;

use OCA\GroupFolders\Mount\GroupMountPoint;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\ResourceMapper;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\UserPrincipal;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Model\ResourcePermissions\ResourcePermissionsList;
use OCA\OrganizationFolders\Model\ResourcePermissions\ResourcePermissionsApplyPlanFactory;
use OCA\OrganizationFolders\Errors\Api\InvalidResourceType;
use OCA\OrganizationFolders\Errors\Api\InvalidResourceName;
use OCA\OrganizationFolders\Errors\Api\ResourceNotFound;
use OCA\OrganizationFolders\Errors\Api\ResourceNameNotUnique;
use OCA\OrganizationFolders\Errors\Api\ResourceCannotBeItsOwnParent;
use OCA\OrganizationFolders\Errors\Api\ResourceCannotBeMovedIntoADifferentOrganizationFolder;
use OCA\OrganizationFolders\Errors\Api\ResourceCannotBeMovedIntoASubResource;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Errors\Api\PrincipalInvalid;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class ResourceService {
	use TTransactional;

	public function __construct(
		protected readonly IDBConnection $db,
		protected readonly LoggerInterface $logger,
		protected readonly IL10N $l10n,
		protected readonly ResourceMapper $mapper,
		protected readonly PathManager $pathManager,
		protected readonly OrganizationProviderManager $organizationProviderManager,
		protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly ContainerInterface $container,
		protected readonly PrincipalFactory $principalFactory,
		protected readonly ResourcePermissionsApplyPlanFactory $resourcePermissionsApplyPlanFactory,
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
	 * @param bool $noRelativePathLookup prefer speed over accuracy by only matching via fileId
	 * @throws ResourceNotFound
	 * @return FolderResource
	 */
	public function findByFilesystemNode(Folder $folder, bool $noRelativePathLookup = false): FolderResource {
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

			if(!$noRelativePathLookup) {
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
			} else {
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
				$this->organizationFolderService->applyAllPermissionsById($organizationFolderId);
			}

			return $resource;
		} else {
			throw new ResourceNameNotUnique();
		}
	}

	/** Use named arguments to call this function */
	public function update(
			int $id,

			?bool $active = null,
			?bool $inheritManagers = null,

			?int $membersAclPermission = null,
			?int $managersAclPermission = null,
			?int $inheritedAclPermission = null,

			?int $maxiumumUsersPermissionsAddedOrDeleted = null,
		): Resource {
		$resource = $this->find($id);

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

		return $this->atomic(function () use ($resource, $maxiumumUsersPermissionsAddedOrDeleted) {
			$resource = $this->mapper->update($resource);

			/** @var PermissionsService */
			$permissionsService = $this->container->get(PermissionsService::class);

			$permissionsService->applyResourcePermissionsAfterResourceUpdate(
				updatedResource: $resource,
				maxiumumUsersPermissionsAddedOrDeleted: $maxiumumUsersPermissionsAddedOrDeleted,
			);

			return $resource;
		}, $this->db);
	}

	public function move(
		Resource $resource,
		string $name,
		?int $parentResourceId,
	) {
		if($resource->getType() === "folder") {
			$resourceNode = $this->getFolderResourceFilesystemNode($resource);

			// aquire lock so nothing changes until ready to actually move the folder
			// TODO: investigate if View->shouldLockFile prevents this from being effective
			$resourceNode->lock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
		}

		if(!$this->isValidResourceName($name)) {
			throw new InvalidResourceName($name);
		}

		if(is_null($parentResourceId)) {
			$parentResource = null;
		} else {
			// trying to set the resource itself as it's parent
			if($parentResourceId === $resource->getId()) {
				throw new ResourceCannotBeItsOwnParent($resource);
			}

			$parentResource = $this->find($parentResourceId);

			// trying to move to different organization folder
			if($parentResource->getOrganizationFolderId() !== $resource->getOrganizationFolderId()) {
				throw new ResourceCannotBeMovedIntoADifferentOrganizationFolder($resource);
			}

			// trying to create a cycle in resource tree
			$resourcesOnPathToNewParent = $this->getAllResourcesOnPathFromRootToResource($parentResource, false);
			foreach($resourcesOnPathToNewParent as $resourceOnPathToNewParent) {
				if($resourceOnPathToNewParent->getId() === $resource->getId()) {
					throw new ResourceCannotBeMovedIntoASubResource($resource);
				}
			}
		}

		if($this->mapper->existsWithName(
			organizationFolderId: $resource->getOrganizationFolderId(),
			parentResourceId: $parentResource?->getId(),
			name: $name,
		)) {
			throw new ResourceNameNotUnique();
		}

		if($resource->getType() === "folder") {
			$oldPath = $resourceNode->getPath();

			if(isset($parentResource)) {
				$parentNode = $this->getFolderResourceFilesystemNode($parentResource);
			} else {
				$parentNode = $this->pathManager->getOrganizationFolderNodeById($resource->getOrganizationFolderId());
			}

			$parentNode->lock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
			
			$newPath = $parentNode->getPath() . "/" . $name;
		}

		$oldName = $resource->getName();
		$oldParentResourceId = $resource->getParentResource();

		$resource->setName($name);
		$resource->setParentResource($parentResource?->getId());

		if(count($resource->getUpdatedFields()) > 0) {
			$resource->setLastUpdatedTimestamp(time());

			try {
				$resource = $this->mapper->update($resource);
			} catch (Exception $e) {
				$this->logger->error(
					message: "Updating resource (id: " . $resource->getId() . ")"
						. " from (name: \"" . $oldName . "\", parentResourceId: " . json_encode($oldParentResourceId) . ") "
						. " from (name: \"" . $name . "\", parentResourceId: " . json_encode($parentResource?->getId()) . ") "
						. "failed, not proceeding with any filesystem changes"
				);

				if($resource->getType() === "folder") {
					$resourceNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
					$parentNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
				}
				throw $e;
			}

			if($resource->getType() === "folder") {
				// release lock, as move will create it's own exclusive locks
				// upgrading locks to exclusive would be better, but that does not seem to be possible
				$resourceNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
				$parentNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);

				try {
					$resourceNode->move($newPath);
				} catch (Exception $e) {
					$this->logger->error(
						message: "Moving FolderResource (id: " . $resource->getId() . ") filesystem node "
							. "from \"" . $oldPath . "\" to \"" . $newPath . "\" failed, "
							. "rolling back changes to resource and filesystem"
					);

					// roll back filesystem changes if neccessary
					$resourceNode->move($oldPath);

					// roll back resource changes
					$resource->setName($oldName);
					$resource->setParentResource($oldParentResourceId);
					$resource = $this->mapper->update($resource);
				}
			}
		} else {
			// no changes need to be made, releasing locks
			if($resource->getType() === "folder") {
				$resourceNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
				$parentNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
			}
		}

		// Moving a resource has big implications for the permissions of other resources,
		// so unlike update() this does not try to be smart about which parts of the resource tree need their permissions
		// recalculated and instead recalculates all permissions in the OrganizationFolder
		$this->organizationFolderService->applyAllPermissionsById($resource->getOrganizationFolderId());

		return $resource;
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

	/**
	 * Get an array of all resources on the path from the root to the given resource (including the given resource unless includeResourceItself = false)
	 * ordered by top-level resource first
	 * @param Resource $resource
	 * @return Resource[]
	 */
	public function getAllResourcesOnPathFromRootToResource(Resource $resource, bool $includeResourceItself = true): array {
		$currentResource = $resource;
		
		if($includeResourceItself) {
			$invertedResourcesPath = [$currentResource];
		} else {
			$invertedResourcesPath = [];
		}
		

		while($currentResource->getParentResource()) {
			$currentResource = $this->find($currentResource->getParentResource());
			$invertedResourcesPath[] = $currentResource;
		}

		return array_reverse($invertedResourcesPath);
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

	/**
	 * @param ResourcePermissionsList[] $permissionsListsAlongPath
	 * @param Principal $principal
	 * @return bool
	 */
	private function principalHasPermissionsAlongFullPath(array $permissionsListsAlongPath, Principal $principal): bool {
		foreach($permissionsListsAlongPath as $permissionsList) {
			$permissions = $permissionsList->getPermissions();

			foreach($permissions as $permission) {
				if($permission->getPermissionsBitmap() > 0) {
					if($permission->getPrincipal()->containsPrincipal(principal: $principal, skipExpensiveOperations: true)) {
						// continue along path to next resource
						continue 2;
					}
				}
			}
			
			// did not continue, the principal has no permissions in this resource
			return false;
		}

		return true;
	}

	public function getPermissionsReport(Resource $resource): array {
		$result = [];

		/** @var PermissionsService */
		$permissionsService = $this->container->get(PermissionsService::class);

		/** @var non-empty-list<ResourcePermissionsList> */
		$permissionsLists = iterator_to_array($permissionsService->generateResourcePermissionsListsAlongPathToResource(resource: $resource, enableOriginTracing: true), false);
		$resourcePermissionsList = array_pop($permissionsLists);

		foreach($resourcePermissionsList->getPermissions() as $permission) {
			if($permission->getPermissionsBitmap() > 0) {
				$principal = $permission->getPrincipal();

				$filteredPermissionOrigins = [];

				foreach($permission->getPermissionOrigins() as $permissionOrigin) {
					if($permissionOrigin["permissionsBitmap"] > 0) {
						// only keep last (least inheritedFrom distance to resource) of each type
						$filteredPermissionOrigins[$permissionOrigin["type"]->value] = $permissionOrigin;
					}
				}

				$warnings = [];

				if($resource->getType() === "folder") {
					// check if principal has permissions on full path
					// (organization folder members do not need to be checked as first level resource members are implicit organization folder members)
					if(!$this->principalHasPermissionsAlongFullPath($permissionsLists, $principal)) {
						if($principal instanceof UserPrincipal) {
							$l10n = $this->l10n->t(
								text: '%1$s does not have permissions along the full path to this folder, they will not be able to navigate to this folder!',
								parameters: [
									$principal->getFriendlyName(),
								]
							);
						} else {
							$l10n = $this->l10n->t(
								text: 'This group does not have permissions along the full path to this folder, but people need to be able to navigate to this folder to get access. Only the group members with other group memberships that allow them to access the parent folders will be able to access this folder.'
							);
						}

						$warnings[] = [
							"type" => "permissions_not_on_full_path",
							"l10n" => $l10n,
						];
					}
				}

				$result[] = [
					'principal' => $principal,
					'permissionsBitmap' => $permission->getPermissionsBitmap(),
					'permissionOrigins' => array_values($filteredPermissionOrigins),
					'warnings' => $warnings,
				];
			}
		}

		return $result;
	}

	public function getUserPermissionsReport(Resource $resource, UserPrincipal $userPrincipal): array {
		if(!$userPrincipal->isValid()) {
			throw new PrincipalInvalid($userPrincipal);
		}

		/** @var PermissionsService */
		$permissionsService = $this->container->get(PermissionsService::class);

		/** @var non-empty-list<ResourcePermissionsList> */
		$permissionsLists = iterator_to_array($permissionsService->generateResourcePermissionsListsAlongPathToResource(resource: $resource), false);
		$resourcePermissionsList = array_pop($permissionsLists);

		$overallPermissionsBitmap = 0;
		$applicablePermissions = [];
		$warnings = [];

		foreach($resourcePermissionsList->getPermissions() as $permission) {
			if($permission->getPermissionsBitmap() > 0) {
				$principal = $permission->getPrincipal();

				if($principal->containsPrincipal($userPrincipal)) {
					$overallPermissionsBitmap |= $permission->getPermissionsBitmap();

					$applicablePermissions[] = [
						'principal' => $principal,
						'permissionsBitmap' => $permission->getPermissionsBitmap(),
					];
				}
			}
		}

		if(!$this->principalHasPermissionsAlongFullPath($permissionsLists, $userPrincipal)) {
			$l10n = $this->l10n->t(
					text: '%1$s does not have permissions along the full path to this folder, they will not be able to navigate to this folder!',
					parameters: [
						$userPrincipal->getFriendlyName(),
					]
			);

			$warnings[] = [
				"type" => "permissions_not_on_full_path",
				"l10n" => $l10n,
			];
		}

		return [
			"applicablePermissions" => $applicablePermissions,
			"overallPermissionsBitmap" => $overallPermissionsBitmap,
			"warnings" => $warnings,
		];
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
