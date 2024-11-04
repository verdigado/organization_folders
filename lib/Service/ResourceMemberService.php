<?php

namespace OCA\OrganizationFolders\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\OrganizationFolders\Errors\ResourceMemberNotFound;

use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Db\ResourceMemberMapper;

use OCA\OrganizationFolders\Enum\MemberPermissionLevel;
use OCA\OrganizationFolders\Enum\MemberType;

class ResourceMemberService {
	public function __construct(
        private ResourceMemberMapper $mapper
    ) {
	}

	public function findAll(int $resourceId): array {
		return $this->mapper->findAll($resourceId);
	}

	private function handleException(Exception $e): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new ResourceMemberNotFound($e->getMessage());
		} else {
			throw $e;
		}
	}

	public function find(int $id): ResourceMember {
		try {
			return $this->mapper->find($id);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function create(
		int $resourceId,
		MemberPermissionLevel $permissionLevel,
		MemberType $type,
		string $principal
	): ResourceMember {
		$member = new ResourceMember();
		$member->setResourceId($resourceId);
		$member->setPermissionLevel($permissionLevel->value);
        $member->setType($type->value);
		
		// TODO: check if principal fits format
        $member->setPrincipal($principal);
        $member->setCreatedTimestamp(time());
        $member->setLastUpdatedTimestamp(time());

		return $this->mapper->insert($member);
	}

	public function update(int $id, ?MemberPermissionLevel $permissionLevel = null, ?MemberType $type = null, ?string $principal = null): ResourceMember {
		try {
			$member = $this->mapper->find($id);

            if(isset($permissionLevel)) {
                $member->setPermissionLevel($permissionLevel->value);
            }

            if(isset($type)) {
                $member->setType($type->value);
            }

            if(isset($principal)) {
                $member->setPrincipal($principal);
            }
			
            if(count($member->getUpdatedFields()) > 0) {
                $member->setLastUpdatedTimestamp(time());
            }

			return $this->mapper->update($member);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function delete(int $id): ResourceMember {
		try {
			$member = $this->mapper->find($id);
			$this->mapper->delete($member);
			return $member;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}