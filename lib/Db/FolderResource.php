<?php

namespace OCA\OrganizationFolders\Db;

class FolderResource extends Resource {
	protected $membersAclPermission;
	protected $managersAclPermission;
	protected $inheritedAclPermission;
	protected $fileId;

	public function __construct() {
		parent::__construct();
		$this->addType('membersAclPermission','integer');
		$this->addType('managersAclPermission','integer');
		$this->addType('inheritedAclPermission','integer');
		$this->addType('fileId','integer');
	}

	public static function fromRow(array $row): static {
		$instance = new static();

		$instance->setId($row["id"]);
		$instance->setParentResource($row["parent_resource"]);
		$instance->setOrganizationFolderId($row["organization_folder_id"]);
		$instance->setName($row["name"]);
		$instance->setActive($row["active"]);
		$instance->setInheritManagers($row["inherit_managers"]);
		$instance->setLastUpdatedTimestamp($row["last_updated_timestamp"]);
		$instance->setMembersAclPermission($row["members_acl_permission"]);
		$instance->setManagersAclPermission($row["managers_acl_permission"]);
		$instance->setInheritedAclPermission($row["inherited_acl_permission"]);
		$instance->setFileId($row["file_id"]);

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
			'inheritManagers' => $this->inheritManagers,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,

			'membersAclPermission' => $this->membersAclPermission,
			'managersAclPermission' => $this->managersAclPermission,
			'inheritedAclPermission' => $this->inheritedAclPermission,
			'fileId' => $this->fileId,
		];
	}

	public function limitedJsonSerialize(): array {
		return [
			'id' => $this->id,
			'parentResource' => $this->parentResource,
			'organizationFolderId' => $this->organizationFolderId,
			'type' => $this->getType(),
			'name' => $this->name,
			'active' => $this->active,
			'inheritManagers' => $this->inheritManagers,
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,
			
			'fileId' => $this->fileId,
		];
	}

	public function tableSerialize(?array $params = null): array {
		return [
			'Id' => $this->id,
			'Name' => $this->name,
			'Type' => "Folder",
			'Active' => ((bool)$this->active) ? 'yes' : 'no',
			'Inherit Managers' =>  ((bool)$this->inheritManagers) ? 'yes' : 'no',
			'Last Updated' => $this->lastUpdatedTimestamp,

			'Members ACL Permission' => $this->membersAclPermission,
			'Managers ACL Permission' => $this->managersAclPermission,
			'Inherited ACL Permission' => $this->inheritedAclPermission,
		];
	}

	public function getType(): string {
		return "folder";
	}
}
