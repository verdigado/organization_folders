<?php

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\Db\ResourceMapper;
use OCA\OrganizationFolders\Errors\InvalidResourceType;
use OCA\OrganizationFolders\Errors\ResourceNotFound;
use OCA\OrganizationFolders\Errors\ResourceNameNotUnique;

class ResourceService {
	public function __construct(
		private ResourceMapper $mapper
	) {
	}

	public function findAll(int $groupfolderId, int $parentResourceId = null) {
		return $this->mapper->findAll($groupfolderId, $parentResourceId);
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
		?int $parentResource = null,
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

		if(!$this->mapper->existsWithName($organizationFolderId, $parentResource, $name)) {
			$resource->setGroupFolderId($organizationFolderId);
			$resource->setName($name);
			$resource->setParentResource($parentResource);
			$resource->setActive($active);
			$resource->setLastUpdatedTimestamp(time());

			if($type === "folder") {
				if(isset($membersAclPermission, $managersAclPermission, $inheritedAclPermission)) {
					$resource->setMembersAclPermission($membersAclPermission);
					$resource->setManagersAclPermission($managersAclPermission);
					$resource->setInheritedAclPermission($inheritedAclPermission);
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
