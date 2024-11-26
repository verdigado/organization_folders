<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

use OCP\AppFramework\Db\Entity;

use OCA\OrganizationFolders\Enum\OrganizationFolderMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\Principal;

class OrganizationFolderMember extends Entity implements JsonSerializable, TableSerializable {
	protected $organizationFolderId;
	protected $permissionLevel;

	/**
	 * @var Principal
	 */
	protected $principal;
    protected $createdTimestamp;
	protected $lastUpdatedTimestamp;
	
	public function __construct() {
		$this->addType('organizationFolderId','integer');
		$this->addType('permissionLevel','integer');
        $this->addType('principalType','integer');
        $this->addType('createdTimestamp','integer');
		$this->addType('lastUpdatedTimestamp','integer');
	}

	public function setPrincipal(Principal $principal) {
		if($principal->getType() === PrincipalType::GROUP || $principal->getType() === PrincipalType::ROLE) {
			if(!isset($this->principal) || $this->principal->getType() !== $principal->getType()) {
				$this->markFieldUpdated("principalType");
				$principalTypeUpdated = true;
			}

			if(!isset($this->principal) || $this->principal->getId() !== $principal->getId()) {
				$this->markFieldUpdated("principalId");
				$principalIdUpdated = true;
			}

			if($principalTypeUpdated || $principalIdUpdated) {
				$this->principal = $principal;
			}
		} else {
			throw new \Exception("individual users are not allowed as organization folder members");
		}
	}

	public function getPrincipalType(): int {
		return $this->principal?->getType()->value;
	}

	public function getPrincipalId(): string|null {
		return $this->principal?->getId();
	}

    public function setPermissionLevel(int $permissionLevel) {
        if($permissionLevel >= 1 && $permissionLevel <= 3) {
			if ($permissionLevel === $this->permissionLevel) {
				// no change
				return;
			}

			$this->markFieldUpdated("permissionLevel");
            $this->permissionLevel = $permissionLevel;
        } else {
            throw new \Exception("invalid organization folder member permission level");
        }
    }

    public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'organizationFolderId' => $this->organizationFolderId,
			'permissionLevel' => $this->permissionLevel,
			'principal' => $this->getPrincipal(),
            'createdTimestamp' => $this->createdTimestamp,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,
		];
	}

    public function tableSerialize(?array $params = null): array {
		return [
			'Id' => $this->id,
			'Organization Folder Id' => $this->organizationFolderId,
			'Permission Level' => OrganizationFolderMemberPermissionLevel::from($this->permissionLevel)->name,
			'Principal Type' => $this->principal?->getType()->name,
            'Principal Id' => $this->principal?->getId(),
			'Principal Friendly Name' => $this->principal?->getFriendlyName(),
            'Created' => $this->createdTimestamp,
			'LastUpdated' => $this->lastUpdatedTimestamp,
		];
	}
}
