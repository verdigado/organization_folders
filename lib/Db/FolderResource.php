<?php

namespace OCA\OrganizationFolders\Db;

use OCP\IL10N;
use OCP\DB\Types;

class FolderResource extends Resource {
	protected $fileId;

	public const PERMISSION_KEYS = ["READ", "UPDATE", "CREATE", "DELETE", "SHARE"];

	public const PERMISSION_READ = 1;

	public const PERMISSION_UPDATE = 2;

	public const PERMISSION_CREATE = 4;

	public const PERMISSION_DELETE = 8;

	public const PERMISSION_SHARE = 16;

	protected const PERMISSIONS_BITFIELD_MAX = 31; // precalculation of pow(2, count(static::PERMISSION_KEYS)) - 1

	public const SUPPORTS_SUBRESOURCES = true;


	public function __construct() {
		parent::__construct();
		$this->addType('fileId', Types::INTEGER);
	}

	public function getType(): string {
		return "folder";
	}

	public function getFileId(): int {
		return $this->fileId;
	}

	public static function fromRow(array $row): static {
		$entity = new static();

		self::fromRowCommon($entity, $row);
		
		$entity->setFileId($row["file_id"]);

		$entity->resetUpdatedFields();

		return $entity;
	}

	public function jsonSerialize(): array {
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
			'memberPermissions' => $this->getMemberPermissions(),
			'managerPermissions' => $this->getManagerPermissions(),
			'inheritedMemberPermissions' => $this->getInheritedMemberPermissions(),

			'fileId' => $this->fileId,
		];
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
			'lastUpdatedTimestamp' => $this->lastUpdatedTimestamp,
			'createdFromTemplateId' => $this->createdFromTemplateId,
			
			'fileId' => $this->fileId,
		];
	}

	public function tableSerialize(IL10N $l10n, ?array $params = null): array {
		return [
			'Id' => $this->id,
			'Name' => $this->name,
			'Type' => $l10n->t("Folder"),
			'Active' => ((bool)$this->active) ? 'yes' : 'no',
			'Inherit Managers' =>  ((bool)$this->inheritManagers) ? 'yes' : 'no',
			'Last Updated' => $this->lastUpdatedTimestamp,

			'Members ACL Permission' => $this->memberPermissionsBitfield,
			'Managers ACL Permission' => $this->managerPermissionsBitfield,
			'Inherited ACL Permission' => $this->inheritedMemberPermissionsBitfield,

			'Created From Template' => $this->createdFromTemplateId,
		];
	}
}
