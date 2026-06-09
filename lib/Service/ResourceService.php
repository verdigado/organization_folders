<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use Exception;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\Files\Folder;

use OCA\GroupFolders\Mount\GroupMountPoint;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\CalendarResource;
use OCA\OrganizationFolders\Db\ResourceMapper;
use OCA\OrganizationFolders\DTO\CreateResourceDto;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\UserPrincipal;
use OCA\OrganizationFolders\Model\PrincipalFactory;
use OCA\OrganizationFolders\Model\ResourcePermissions\ResourcePermissionsList;
use OCA\OrganizationFolders\Model\ResourcePermissions\ResourcePermissionsApplyPlanFactory;
use OCA\OrganizationFolders\Errors\Api\InvalidResourceType;
use OCA\OrganizationFolders\Errors\Api\InvalidResourceName;
use OCA\OrganizationFolders\Errors\Api\ResourceNotFound;
use OCA\OrganizationFolders\Errors\Api\ResourceNameNotUnique;
use OCA\OrganizationFolders\Errors\Api\ResourceDoesNotSupportSubresources;
use OCA\OrganizationFolders\Errors\Api\ResourceCannotBeItsOwnParent;
use OCA\OrganizationFolders\Errors\Api\ResourceCannotBeMovedIntoADifferentOrganizationFolder;
use OCA\OrganizationFolders\Errors\Api\ResourceCannotBeMovedIntoASubResource;
use OCA\OrganizationFolders\Errors\Api\OrganizationFolderNotFound;
use OCA\OrganizationFolders\Errors\Api\PrincipalInvalid;
use OCA\OrganizationFolders\Errors\Api\ResourceTypeNotEnabled;
use OCA\OrganizationFolders\Errors\Api\ActionCancelled;
use OCA\OrganizationFolders\Events\BeforeResourceCreatedEvent;
use OCA\OrganizationFolders\Events\BeforeResourceDeletedEvent;
use OCA\OrganizationFolders\Events\BeforeResourceMovedEvent;
use OCA\OrganizationFolders\Events\BeforeResourceUpdatedEvent;
use OCA\OrganizationFolders\Manager\PathManager;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;
use OCA\OrganizationFolders\Integration\Dav\CalendarIntegration;

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
		protected readonly CalendarIntegration $calendarIntegration,
		protected readonly IEventDispatcher $eventDispatcher,
		protected readonly IUserSession $userSession,
	) {
	}

	/**
	 * @param int $organizationFolderId
	 * @param int|null $parentResourceId
	 * @param array $filters
	 * @return Resource[]
	 */
	public function findAll(int $organizationFolderId, ?int $parentResourceId = null, array $filters = []): array {
		return $this->mapper->findAll($organizationFolderId, $parentResourceId, $filters);
	}

	private function handleException(Exception $e, array $criteria): Exception {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			return new ResourceNotFound($criteria);
		} else {
			return $e;
		}
	}

	public function find(int $id): Resource {
		try {
			return $this->mapper->find($id);
		} catch (Exception $e) {
			throw $this->handleException($e, ["id" => $id]);
		}
	}

	public function findByFileId(int $fileId): FolderResource {
		try {
			return $this->mapper->findByFileId($fileId);
		} catch (Exception $e) {
			throw $this->handleException($e, ["fileId" => $fileId]);
		}
	}

	public function findByName(int $organizationFolderId, ?int $parentResourceId, string $name): Resource {
		try {
			return $this->mapper->findByName($organizationFolderId, $parentResourceId, $name);
		} catch (Exception $e) {
			throw $this->handleException($e, [
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

	public function existsWithName(int $organizationFolderId, ?int $parentResourceId, string $name): bool {
		return $this->mapper->existsWithName($organizationFolderId, $parentResourceId, $name);
	}

	public function existAnyCreatedFromTemplate(int $organizationFolderId, string $providerId, string $templateId): bool {
		return $this->mapper->existAnyCreatedFromTemplate($organizationFolderId, $providerId, $templateId);
	}

	public function isValidResourceName(string $name): bool {
		return (strlen($name) >= 3) && (!preg_match('/[`$%^*={};"\\\\|<>\/?~]/', $name));
	}

	/**
	 * @param CreateResourceDto $createResourceDto
	 * @return Resource
	 */
	public function createFromDTO(
		CreateResourceDto $createResourceDto,

		?string $createdFromTemplateId = null,
	) {
		return $this->create(
			type: $createResourceDto->type,
			organizationFolderId: $createResourceDto->organizationFolderId,
			name: $createResourceDto->name,
			parentResourceId: $createResourceDto->parentResourceId,
			active: $createResourceDto->active,
			inheritManagers: $createResourceDto->inheritManagers,

			memberPermissions: $createResourceDto->memberPermissions,
			managerPermissions: $createResourceDto->managerPermissions,
			inheritedMemberPermissions: $createResourceDto->inheritedMemberPermissions,

			createdFromTemplateId: $createdFromTemplateId,
		);
	}

	/**
	 * Use named arguments to call this function!
	 * 
	 * @param bool $alreadyExists special mode, that for type = "folder" uses an existing folder with the resource name
	 *                            and for type = "calendar" uses an existing calendar with the given id $existingCalendarId
	 * 
	 * @param ?string $calendarUri special mode only for type = "calendar" to use a specific calendar URI instead of generating one
	 * 
	 * @param ?bool $skipPermssionsApply Do not apply permissions after making changes
	 * 
	 * @throws InvalidResourceType
	 * @throws InvalidResourceName
	 * @throws ResourceDoesNotSupportSubresources
	 * @throws ResourceNameNotUnique
	 * @return Resource
	 */
	public function create(
		string $type,
		int $organizationFolderId,
		string $name,
		?int $parentResourceId,
		bool $active,
		bool $inheritManagers,
		array $memberPermissions,
		array $managerPermissions,
		array $inheritedMemberPermissions,
		?string $createdFromTemplateId = null,

		bool $alreadyExists = false,
		?int $existingCalendarId = null,

		?string $calendarUri = null,

		?bool $skipPermssionsApply = false,
	) {
		$organizationFolder = $this->organizationFolderService->find($organizationFolderId);

		if(!in_array($type, $organizationFolder->getEnabledResourceTypes())) {
			throw new ResourceTypeNotEnabled($organizationFolder, $type);
		}

		if($type === "folder") {
			$resource = new FolderResource();
		} else if ($type === "calendar") {
			$resource = new CalendarResource();
		} else {
			throw new InvalidResourceType($type);
		}

		if(!$this->isValidResourceName($name)) {
			throw new InvalidResourceName($name);
		}

		if($this->mapper->existsWithName($organizationFolderId, $parentResourceId, $name)) {
			throw new ResourceNameNotUnique();
		}

		$resource->setOrganizationFolderId($organizationFolderId);
		$resource->setName($name);
		$resource->setActive($active);
		$resource->setInheritManagers($inheritManagers);
		$resource->setMemberPermissions($memberPermissions);
		$resource->setManagerPermissions($managerPermissions);
		$resource->setInheritedMemberPermissions($inheritedMemberPermissions);
		$resource->setCreatedTimestamp(time());
		$resource->setLastUpdatedTimestamp(time());
		$resource->setCreatedFromTemplateId($createdFromTemplateId);

		if(isset($parentResourceId)) {
			$parentResource = $this->find($parentResourceId);

			if($parentResource->getOrganizationFolderId() === $organizationFolderId) {
				if(!$parentResource::SUPPORTS_SUBRESOURCES) {
					throw new ResourceDoesNotSupportSubresources($parentResource);
				} else {
					$resource->setParentResource($parentResource->getId());
				}
			} else {
				throw new Exception("Cannot create child-resource of parent in different organization folder");
			}

			$parentNode = $this->getFolderResourceFilesystemNode($parentResource);
		} else {
			$parentNode = $this->pathManager->getOrganizationFolderRootNodeById($organizationFolderId);
		}

		// emit cancellable event before doing any changes
        $beforeEvent = new BeforeResourceCreatedEvent(
            $resource,
            $this->userSession->getUser()?->getUID(),
        );
        $this->eventDispatcher->dispatchTyped($beforeEvent);
        if ($beforeEvent->isCancelled()) {
            throw new ActionCancelled(
                $beforeEvent->getErrorMessage(),
            );
        }

		if($type === "folder") {
			if($alreadyExists) {
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

			$resource->setFileId($fileId);
		} else if ($type === "calendar") {
			if($alreadyExists) {
				$calendar = $this->calendarIntegration->getCalendarById($existingCalendarId);

				if(!isset($calendar)) {
					throw new Exception("Existing calendar not found");
				}

				if($calendar['principaluri'] !== "principals/users/" . $organizationFolder->getServiceAccountUid()) {
					throw new Exception("Existing calendar needs to be owned by organization folder service account");
				}

				$this->calendarIntegration->updateCalendar($calendar["id"], displayname: $name, description: "");
			} else {
				$calendar = $this->calendarIntegration->createCalendar(
					principalUri: "principals/users/" . $organizationFolder->getServiceAccountUid(),
					calendarUri: $calendarUri ?? hash('sha256', $name . "_" . time()),
					displayname: $name,
					description: "",
				);
			}

			$resource->setCalendarId($calendar["id"]);
		}

		try {
			$resource = $this->mapper->insert($resource);
		} catch (\Throwable $e) {
			// an error occured, rewind all changes, depending on resource type
			if(!$alreadyExists) {
				if($type === "folder") {
					$resourceNode?->delete();
				} else if ($type === "calendar") {
					$this->calendarIntegration->deleteCalendar($calendar["id"]);
				}
			}
			throw $e;
		}

		if(!$skipPermssionsApply) {
			$this->organizationFolderService->applyAllPermissionsById($organizationFolderId);
		}

		return $resource;
	}

	/**
	 * Use named arguments to call this function
	 * 
	 * @param array<string, bool> $memberPermissions
	 * @param array<string, bool> $managerPermissions
	 * @param array<string, bool> $inheritedMemberPermissions
	 * @param bool $permissionsPatchMode if true permissions keys that are unset are kept at current value; if false unset values default to false
	 * @return Resource
	 */
	public function update(
			int $id,

			?bool $active = null,
			?bool $inheritManagers = null,

			?array $memberPermissions = null,
			?array $managerPermissions = null,
			?array $inheritedMemberPermissions = null,
			bool $permissionsPatchMode = true,

			?int $maxiumumUsersPermissionsAddedOrDeleted = null,
		): Resource {
		$resource = $this->find($id);

		if($permissionsPatchMode) {
			if(isset($memberPermissions)) {
				$memberPermissionsBitfield = $resource::patchPermissionsBitfield($resource->getMemberPermissionsBitfield(), $memberPermissions);
			}

			if(isset($managerPermissions)) {
				$managerPermissionsBitfield = $resource::patchPermissionsBitfield($resource->getManagerPermissionsBitfield(), $managerPermissions);
			}

			if(isset($inheritedMemberPermissions)) {
				$inheritedMemberPermissionsBitfield = $resource::patchPermissionsBitfield($resource->getInheritedMemberPermissionsBitfield(), $inheritedMemberPermissions);
			}
		} else {
			if(isset($memberPermissions)) {
				$memberPermissionsBitfield = $resource::permissionsToBitfield($memberPermissions);
			}

			if(isset($managerPermissions)) {
				$managerPermissionsBitfield = $resource::permissionsToBitfield($managerPermissions);
			}

			if(isset($inheritedMemberPermissions)) {
				$inheritedMemberPermissionsBitfield = $resource::permissionsToBitfield($inheritedMemberPermissions);
			}
		}

		// emit cancellable event before doing any changes
		$beforeEvent = new BeforeResourceUpdatedEvent(
			$resource,
			$active,
			$inheritManagers,
			isset($memberPermissionsBitfield) ? $resource::bitfieldToPermissions($memberPermissionsBitfield) : null,
			isset($managerPermissionsBitfield) ? $resource::bitfieldToPermissions($managerPermissionsBitfield) : null,
			isset($inheritedMemberPermissionsBitfield) ? $resource::bitfieldToPermissions($inheritedMemberPermissionsBitfield) : null,
			$this->userSession->getUser()?->getUID(),
		);
		$this->eventDispatcher->dispatchTyped($beforeEvent);
		if ($beforeEvent->isCancelled()) {
			throw new ActionCancelled(
				$beforeEvent->getErrorMessage(),
			);
		}

		if(isset($active)) {
			$resource->setActive($active);
		}

		if(isset($inheritManagers)) {
			$resource->setInheritManagers($inheritManagers);
		}

		if(isset($memberPermissionsBitfield)) {
			$resource->setMemberPermissionsBitfield($memberPermissionsBitfield);
		}

		if(isset($managerPermissionsBitfield)) {
			$resource->setManagerPermissionsBitfield($managerPermissionsBitfield);
		}

		if(isset($inheritedMemberPermissionsBitfield)) {
			$resource->setInheritedMemberPermissionsBitfield($inheritedMemberPermissionsBitfield);
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
		if($resource instanceof FolderResource) {
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

			// trying to move into non-folder resource
			if($parentResource->getType() !== "folder") {
				throw new ResourceDoesNotSupportSubresources($parentResource);
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

		if($resource instanceof FolderResource) {
			$oldPath = $resourceNode->getPath();

			if(isset($parentResource)) {
				$parentNode = $this->getFolderResourceFilesystemNode($parentResource);
			} else {
				$parentNode = $this->pathManager->getOrganizationFolderRootNodeById($resource->getOrganizationFolderId());
			}

			$parentNode->lock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
			
			$newPath = $parentNode->getPath() . "/" . $name;
		}

		$oldName = $resource->getName();
		$oldParentResourceId = $resource->getParentResourceId();

		$beforeEvent = new BeforeResourceMovedEvent(
			$resource,
			$name,
			$parentResourceId,
			$this->userSession->getUser()?->getUID(),
		);
		$this->eventDispatcher->dispatchTyped($beforeEvent);
		if ($beforeEvent->isCancelled()) {
			if($resource instanceof FolderResource) {
				$resourceNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
				$parentNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
			}
			
			throw new ActionCancelled(
				$beforeEvent->getErrorMessage(),
			);
		}

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

				if($resource instanceof FolderResource) {
					$resourceNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
					$parentNode->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);
				}
				throw $e;
			}

			if($resource instanceof FolderResource) {
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
			} else if ($resource instanceof CalendarResource) {
				$this->calendarIntegration->updateCalendar($resource->getCalendarId(), $name, "");
			}
		} else {
			// no changes need to be made, releasing locks
			if($resource instanceof FolderResource) {
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

	/**
	 * @param Resource $resource
	 * @return string[]
	 */
	public function getResourcePath(Resource $resource): array {
		$currentResource = $resource;
		
		$invertedPath = [];

		$invertedPath[] = $currentResource->getName();

		while($currentResource->getParentResourceId()) {
			$currentResource = $this->find($currentResource->getParentResourceId());
			$invertedPath[] = $currentResource->getName();
		}

		return array_reverse($invertedPath);
	}

	public function getFolderResourceFilesystemNode(FolderResource $resource) {
		$result = $this->pathManager->getOrganizationFolderByIdSubfolder($resource->getOrganizationFolderId(), $this->getResourcePath($resource));

		if($result->getId() !== $resource->getFileId()) {
			throw new Exception("Invalid state: FolderResource Node has different ID than expected");
		}

		return $result;
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
		if(!is_null($resource->getParentResourceId())) {
			return $this->find($resource->getParentResourceId());
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
		

		while($currentResource->getParentResourceId()) {
			$currentResource = $this->find($currentResource->getParentResourceId());
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
			active: true,
			inheritManagers: true,
			// match current permissions in subfolder
			inheritedMemberPermissions: $resource->getMemberPermissions(), // Members in parent resource will be inherited members in new resource
			memberPermissions: $resource->getMemberPermissions(),
			managerPermissions: $resource->getManagerPermissions(),
			alreadyExists: true,
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
						$filteredPermissionOrigins[$permissionOrigin["type"]->value] = [
							"type" => $permissionOrigin["type"],
							"permissions" => $resource->bitfieldToPermissions($permissionOrigin["permissionsBitmap"]),
							"inheritedFrom" => $permissionOrigin["inheritedFrom"],
						];
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
					'permissions' => $resource->bitfieldToPermissions($permission->getPermissionsBitmap()),
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
						'permissions' => $resource->bitfieldToPermissions($permission->getPermissionsBitmap()),
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
			"overallPermissions" => $resource->bitfieldToPermissions($overallPermissionsBitmap),
			"warnings" => $warnings,
		];
	}

	public function deleteById(int $id): Resource {
		try {
			$resource = $this->mapper->find($id);
			return $this->delete($resource);
		} catch (Exception $e) {
			throw $this->handleException($e, ["id" => $id]);
		}
	}

	public function delete(Resource $resource): Resource {
		return $this->atomic(function () use ($resource): Resource {
			$deleteStack = [];

			$this->recursiveBeforeDeleteEvent($resource, $deleteStack);

			foreach($deleteStack as $deleteResource) {
				if($deleteResource instanceof FolderResource) {
					// delete in filesystem if type folder
					$node = $this->getFolderResourceFilesystemNode($deleteResource);
					
					if(isset($node)) {
						$node->delete();
					} else {
						$this->logger->warning(
							"Tried deleting filesystem node of resource "
							. json_encode($deleteResource)
							. ", but it did not exist. This should not happen, investigate the cause! Proceeding normally."
						);
					}
				} else if($deleteResource instanceof CalendarResource) {
					$this->calendarIntegration->deleteCalendar($deleteResource->getCalendarId());
				}
				
				// delete in database
				$this->mapper->delete($deleteResource);
			}

			return $resource;
		}, $this->db);
	}

	private function recursiveBeforeDeleteEvent(Resource $resource, array &$deleteStack) {
		$beforeEvent = new BeforeResourceDeletedEvent(
			$resource,
			$this->userSession->getUser()?->getUID(),
		);
		$this->eventDispatcher->dispatchTyped($beforeEvent);
		if ($beforeEvent->isCancelled()) {
			throw new ActionCancelled(
				$beforeEvent->getErrorMessage(),
			);
		}
		
		$subResources = $this->getSubResources($resource);
		
		foreach($subResources as $subResource) {
			$this->recursiveBeforeDeleteEvent($subResource, $deleteStack);
		}

		array_push($deleteStack, $resource);
	}
}
