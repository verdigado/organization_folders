<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

use OCP\AppFramework\Db\Entity;

abstract class Resource extends Entity implements JsonSerializable, TableSerializable {
	protected $organizationFolderId;
	protected $parentResource;
	protected $name;
	protected $active;
	protected $inheritManagers;
	protected $createdTimestamp;
	protected $lastUpdatedTimestamp;
	
	public function __construct() {
		$this->addType('organizationFolderId','integer');
		$this->addType('parentResource','integer');
		$this->addType('active','bool');
		$this->addType('inheritManagers','bool');
		$this->addType('createdTimestamp','integer');
		$this->addType('lastUpdatedTimestamp','integer');
	}

	abstract public function getType(): string;

	public function limitedJsonSerialize(): array {
		return [
			'id' => $this->id,
			'parentResource' => $this->parentResource,
			'organizationFolderId' => $this->organizationFolderId,
			'type' => $this->getType(),
			'name' => $this->name,
			'active' => $this->active,
			'inheritManagers' => $this->inheritManagers,
			'createdTimestamp' => $this->createdTimestamp,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,
		];
	}
}
