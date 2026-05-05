<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

use OCP\IL10N;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\Principal;

class ResourceMember extends Entity implements JsonSerializable, TableSerializable {
	protected $resourceId;
	protected $permissionLevel;

	/**
	 * @var Principal
	 */
	protected $principal;
	protected $createdTimestamp;
	protected $lastUpdatedTimestamp;
	
	public function __construct() {
		$this->addType('resourceId', Types::INTEGER);
		$this->addType('permissionLevel', Types::INTEGER);
		$this->addType('principalType', Types::INTEGER);
		$this->addType('createdTimestamp', Types::INTEGER);
		$this->addType('lastUpdatedTimestamp', Types::INTEGER);
	}

	public function setPrincipal(Principal $principal) {
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
	}

	public function getPrincipalType(): int {
		return $this->principal?->getType()->value;
	}

	public function getPrincipalId(): string|null {
		return $this->principal?->getId();
	}

    public function getResourceId(): int {
        return $this->resourceId;
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
			'principal' => $this->principal,
			'createdTimestamp' => $this->createdTimestamp,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,
		];
	}

	public function tableSerialize(IL10N $l10n, ?array $params = null): array {
		return [
			'Id' => $this->id,
			'Resource Id' => $this->resourceId,
			'Permission Level' => ResourceMemberPermissionLevel::from($this->permissionLevel)->name,
			'Principal Type' => $this->principal?->getType()->name,
			'Principal Id' => $this->principal?->getId(),
			'Principal Friendly Name' => $this->principal?->getFriendlyName(),
			'Created' => $this->createdTimestamp,
			'LastUpdated' => $this->lastUpdatedTimestamp,
		];
	}
}
