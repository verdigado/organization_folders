<?php

namespace OCA\OrganizationFolders\Db;

class FolderResource extends Resource {
	protected $membersAclPermission;
	protected $managersAclPermission;
	protected $inheritedAclPermission;

	public function __construct() {
        parent::__construct();
		$this->addType('membersAclPermission','integer');
		$this->addType('managersAclPermission','integer');
		$this->addType('inheritedAclPermission','integer');
	}

	public static function fromRow(array $row): static {
		$instance = new static();

		$instance->setId($row["id"]);
		$instance->setParentResource($row["parent_resource"]);
		$instance->setOrganizationFolderId($row["organization_folder_id"]);
		$instance->setName($row["name"]);
		$instance->setActive($row["active"]);
		$instance->setLastUpdatedTimestamp($row["last_updated_timestamp"]);
		$instance->setMembersAclPermission($row["members_acl_permission"]);
		$instance->setManagersAclPermission($row["managers_acl_permission"]);
		$instance->setInheritedAclPermission($row["inherited_acl_permission"]);

		$instance->resetUpdatedFields();

		return $instance;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'parentResource' => $this->parentResource,
			'organizationFolderId' => $this->organizationFolderId,
			'type' => "folder",
			'name' => $this->name,
			'active' => $this->active,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,

			'membersAclPermission' => $this->membersAclPermission,
			'managersAclPermission' => $this->managersAclPermission,
			'inheritedAclPermission' => $this->inheritedAclPermission,
		];
	}
}
