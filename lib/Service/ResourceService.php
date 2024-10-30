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

class ResourceService {
	public function __construct(
		private ResourceMapper $mapper
	) {
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

		$resource->setGroupFolderId($groupFolderId);
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
	}

	/* Use named arguments to call this function */
	public function update(
			int $id,

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
