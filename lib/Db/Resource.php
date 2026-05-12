<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;

use OCA\OrganizationFolders\Interface\TableSerializable;
use OCA\OrganizationFolders\Errors\Api\ResourcePermissionsBitfieldInvalid;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use OCP\L10N\IFactory;

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
	protected $memberPermissionsBitfield;
	protected $managerPermissionsBitfield;
	protected $inheritedMemberPermissionsBitfield;

	/* Set by child-classes */
	public const PERMISSION_KEYS = [];
	protected const PERMISSIONS_BITFIELD_MAX = 0;
	
	public function __construct() {
		$this->addType('organizationFolderId', Types::INTEGER);
		$this->addType('parentResource', Types::INTEGER);
		$this->addType('active', Types::BOOLEAN);
		$this->addType('inheritManagers', Types::BOOLEAN);
		$this->addType('createdTimestamp', Types::INTEGER);
		$this->addType('lastUpdatedTimestamp', Types::INTEGER);
		$this->addType('createdFromTemplateId', Types::STRING);
		$this->addType('memberPermissionsBitfield', Types::INTEGER);
		$this->addType('managerPermissionsBitfield', Types::INTEGER);
		$this->addType('inheritedMemberPermissionsBitfield', Types::INTEGER);
	}

	protected static function fromRowCommon(Resource &$entity, array $row) {
		$entity->setId($row["id"]);
		$entity->setParentResource($row["parent_resource"]);
		$entity->setOrganizationFolderId($row["organization_folder_id"]);
		$entity->setName($row["name"]);
		$entity->setActive($row["active"]);
		$entity->setInheritManagers($row["inherit_managers"]);
		$entity->setCreatedTimestamp($row["created_timestamp"]);
		$entity->setLastUpdatedTimestamp($row["last_updated_timestamp"]);
		$entity->setCreatedFromTemplateId($row["created_from_template_id"]);
		$entity->setMemberPermissionsBitfield($row["member_permissions_bitfield"]);
		$entity->setManagerPermissionsBitfield($row["manager_permissions_bitfield"]);
		$entity->setInheritedMemberPermissionsBitfield($row["inherited_member_permissions_bitfield"]);
	}

	abstract public function getType(): string;

    public function getOrganizationFolderId(): int {
        return $this->organizationFolderId;
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

	public function getCreatedFromTemplateId(): ?string {
		return $this->createdFromTemplateId;
	}

	public function getMemberPermissionsBitfield(): int {
		return $this->memberPermissionsBitfield;
	}

	public function getManagerPermissionsBitfield(): int {
		return $this->managerPermissionsBitfield;
	}

	public function getInheritedMemberPermissionsBitfield(): int {
		return $this->inheritedMemberPermissionsBitfield;
	}

	private function ensurePermissionsBitfieldValid(int $bitfield): void {
		if($bitfield < 0 || $bitfield > static::PERMISSIONS_BITFIELD_MAX) {
			throw new ResourcePermissionsBitfieldInvalid($bitfield, 0, static::PERMISSIONS_BITFIELD_MAX);
		}
	}

	public function setMemberPermissionsBitfield(int $bitfield) {
		$this->ensurePermissionsBitfieldValid($bitfield);

		if ($bitfield === $this->memberPermissionsBitfield) {
			// no change
			return;
		}

		$this->markFieldUpdated("memberPermissionsBitfield");
		$this->memberPermissionsBitfield = $bitfield;
	}

	public function setManagerPermissionsBitfield(int $bitfield) {
		$this->ensurePermissionsBitfieldValid($bitfield);

		if ($bitfield === $this->managerPermissionsBitfield) {
			// no change
			return;
		}

		$this->markFieldUpdated("managerPermissionsBitfield");
		$this->managerPermissionsBitfield = $bitfield;
	}

	public function setInheritedMemberPermissionsBitfield(int $bitfield) {
		$this->ensurePermissionsBitfieldValid($bitfield);

		if ($bitfield === $this->inheritedMemberPermissionsBitfield) {
			// no change
			return;
		}

		$this->markFieldUpdated("inheritedMemberPermissionsBitfield");
		$this->inheritedMemberPermissionsBitfield = $bitfield;
	}

	/**
	 * @param int $bitfield
	 * @return array
	 */
	private function bitfieldToPermissions(int $bitfield): array {
		$permissions = [];
		
		foreach(static::PERMISSION_KEYS as $index => $key) {
			$permissions[$key] = !!($bitfield & (1 << $index));
		}

		return $permissions;
	}

	/**
	 * @return array<string, bool>
	 */
	public function getMemberPermissions(): array {
		return $this->bitfieldToPermissions($this->memberPermissionsBitfield);
	}

	/**
	 * @return array<string, bool>
	 */
	public function getManagerPermissions(): array {
		return $this->bitfieldToPermissions($this->managerPermissionsBitfield);
	}

	/**
	 * @return array<string, bool>
	 */
	public function getInheritedMemberPermissions(): array {
		return $this->bitfieldToPermissions($this->inheritedMemberPermissionsBitfield);
	}

	/**
	 * @param array $permissions
	 * @return int
	 */
	private function permissionsToBitfield(array $permissions): int {
		$bitfield = 0;

		foreach(static::PERMISSION_KEYS as $index => $key) {
			if($permissions[$key]) {
				$bitfield |= (1 << $index);
			}
		}

		return $bitfield;
	}

	/**
	 * @param array<string, bool> $permissions
	 * @return void
	 */
	public function setMemberPermissions(array $permissions): void {
		$this->setMemberPermissionsBitfield($this->permissionsToBitfield($permissions));
	}

	/**
	 * @param array<string, bool> $permissions
	 * @return void
	 */
	public function setManagerPermissions(array $permissions): void {
		$this->setManagerPermissionsBitfield($this->permissionsToBitfield($permissions));
	}

	/**
	 * @param array<string, bool> $permissions
	 * @return void
	 */
	public function setInheritedMemberPermissions(array $permissions): void {
		$this->setInheritedMemberPermissionsBitfield($this->permissionsToBitfield($permissions));
	}

	/**
	 * @param int $bitfield old value
	 * @param array $permissions patches
	 * @return int new value
	 */
	private function patchPermissionsBitfield(int $bitfield, array $permissions): int {
		foreach(static::PERMISSION_KEYS as $index => $key) {
			if(isset($permissions[$key])) {
				if($permissions[$key]) {
					$bitfield |= (1 << $index);
				} else {
					$bitfield &= ~(1 << $index);
				}
			}
		}

		return $bitfield;
	}

	/**
	 * @param array<string, bool> $permissions
	 * @return void
	 */
	public function patchMemberPermissions(array $permissions): void {
		$this->setMemberPermissionsBitfield($this->patchPermissionsBitfield($this->memberPermissionsBitfield, $permissions));
	}

	/**
	 * @param array<string, bool> $permissions
	 * @return void
	 */
	public function patchManagerPermissions(array $permissions): void {
		$this->setManagerPermissionsBitfield($this->patchPermissionsBitfield($this->managerPermissionsBitfield, $permissions));
	}

	/**
	 * @param array<string, bool> $permissions
	 * @return void
	 */
	public function patchInheritedMemberPermissions(array $permissions): void {
		$this->setInheritedMemberPermissionsBitfield($this->patchPermissionsBitfield($this->inheritedMemberPermissionsBitfield, $permissions));
	}
}
