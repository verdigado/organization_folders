<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

use OCP\AppFramework\Db\Entity;

use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\Model\Principal;

class ResourceMember extends Entity implements JsonSerializable, TableSerializable {
	protected $resourceId;
	protected $permissionLevel;
	protected $principalType;
    protected $principalId;
    protected $createdTimestamp;
	protected $lastUpdatedTimestamp;
	
	public function __construct() {
		$this->addType('resourceId','integer');
		$this->addType('permissionLevel','integer');
        $this->addType('principalType','integer');
        $this->addType('createdTimestamp','integer');
		$this->addType('lastUpdatedTimestamp','integer');
	}
	
	public function getPrincipal(): Principal {
		return new Principal(PrincipalType::from($this->principalType), $this->principalId);
	}

	public function setPrincipal(Principal $principal) {
		$this->setPrincipalType($principal->getType()->value);
        $this->setPrincipalId($principal->getId());
	}

	public function setPermissionLevel(int $permissionLevel) {
        if($permissionLevel >= 1 && $permissionLevel <= 2) {
			if ($permissionLevel === $this->permissionLevel) {
				// no change
				return;
			}

			$this->markFieldUpdated("permissionLevel");
            $this->permissionLevel = $permissionLevel;
		} else {
            throw new \Exception("invalid resource member permission level");
        }
    }

    public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'resourceId' => $this->resourceId,
			'permissionLevel' => $this->permissionLevel,
			'principal' => $this->getPrincipal(),
            'createdTimestamp' => $this->createdTimestamp,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,
		];
	}

    public function tableSerialize(?array $params = null): array {
		return [
			'Id' => $this->id,
			'Resource Id' => $this->resourceId,
			'Permission Level' => ResourceMemberPermissionLevel::from($this->permissionLevel)->name,
			'Principal Type' => PrincipalType::from($this->principalType)->name,
            'Principal Id' => $this->principalId,
            'Created' => $this->createdTimestamp,
			'LastUpdated' => $this->lastUpdatedTimestamp,
		];
	}
}
