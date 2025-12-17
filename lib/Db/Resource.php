<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

abstract class Resource extends Entity implements JsonSerializable, TableSerializable {
	protected $organizationFolderId;

	/* TODO: rename to parentResourceId */
	protected $parentResource;
	protected $name;
	protected $active;
	protected $inheritManagers;
	protected $createdTimestamp;
	protected $lastUpdatedTimestamp;
	protected $createdFromTemplateId;
	
	public function __construct() {
		$this->addType('organizationFolderId', Types::INTEGER);
		$this->addType('parentResource', Types::INTEGER);
		$this->addType('active', Types::BOOLEAN);
		$this->addType('inheritManagers', Types::BOOLEAN);
		$this->addType('createdTimestamp', Types::INTEGER);
		$this->addType('lastUpdatedTimestamp', Types::INTEGER);
		$this->addType('createdFromTemplateId', Types::STRING);
	}

	abstract public function getType(): string;

    public function getOrganizationFolderId(): int {
        return $this->organizationFolderId;
    }

	/**
	 * @deprecated use getParentResourceId()
	 * @return int
	 */
	public function getParentResource(): ?int {
		return $this->parentResource;
	}

	public function getParentResourceId(): ?int {
		return $this->parentResource;
	}

	public function setParentResourceId(?int $newParentResourceId): void {
		$this->setParentResource($newParentResourceId);
	}

	public function getActive(): bool {
		return $this->active;
	}

	public function getInheritManagers(): bool {
		return $this->inheritManagers;
	}

	public function getCreatedTimestamp(): int {
		return $this->createdTimestamp;
	}

	public function getLastUpdatedTimestamp(): int {
		return $this->lastUpdatedTimestamp;
	}

	public function getCreatedFromTemplateId(): string {
		return $this->createdFromTemplateId;
	}

	public function limitedJsonSerialize(): array {
		return [
			'id' => $this->id,
			'parentResourceId' => $this->parentResource,
			'organizationFolderId' => $this->organizationFolderId,
			'type' => $this->getType(),
			'name' => $this->name,
			'active' => $this->active,
			'inheritManagers' => $this->inheritManagers,
			'createdTimestamp' => $this->createdTimestamp,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,
			'createdFromTemplateId' => $this->createdFromTemplateId,
		];
	}
}
