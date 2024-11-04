<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

use OCP\AppFramework\Db\Entity;

use OCA\OrganizationFolders\Enum\MemberPermissionLevel;
use OCA\OrganizationFolders\Enum\MemberType;

class ResourceMember extends Entity implements JsonSerializable, TableSerializable {
	protected $resourceId;
	protected $permissionLevel;
	protected $type;
    protected $principal;
    protected $createdTimestamp;
	protected $lastUpdatedTimestamp;
	
	public function __construct() {
		$this->addType('resourceId','integer');
		$this->addType('permissionLevel','integer');
        $this->addType('type','integer');
        $this->addType('createdTimestamp','integer');
		$this->addType('lastUpdatedTimestamp','integer');
	}

    public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'resourceId' => $this->resourceId,
			'permissionLevel' => $this->permissionLevel,
			'type' => $this->type,
            'principal' => $this->principal,
            'createdTimestamp' => $this->createdTimestamp,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,
		];
	}

    public function tableSerialize(?array $params = null): array {
		return [
			'Id' => $this->id,
			'Resource Id' => $this->resourceId,
			'Permission Level' => MemberPermissionLevel::from($this->permissionLevel)->name,
			'Type' => MemberType::from($this->type)->name,
            'Principal' => $this->principal,
            'Created' => $this->createdTimestamp,
			'LastUpdated' => $this->lastUpdatedTimestamp,
		];
	}
}
