<?php

namespace OCA\OrganizationFolders\Db;

use OCP\IL10N;
use OCP\DB\Types;

class CalendarResource extends Resource {
	protected $calendarId;

	public const PERMISSION_KEYS = ["READ", "UPDATE"];

	public const PERMISSION_READ = 1;
	
	public const PERMISSION_UPDATE = 2;

	protected const PERMISSIONS_BITFIELD_MAX = 3; // precalculation of pow(2, count(static::PERMISSION_KEYS)) - 1

	public const SUPPORTS_LINK_SHARES = true;

	public function __construct() {
		parent::__construct();
		$this->addType('calendarId', Types::INTEGER);
	}

    public function getType(): string {
		return "calendar";
	}

	public function getCalendarId(): int {
		return $this->calendarId;
	}

	public static function fromRow(array $row): static {
		$entity = new static();

		self::fromRowCommon($entity, $row);
		
		$entity->setCalendarId($row["calendar_id"]);

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
			
			'calendarId' => $this->calendarId,
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
			
			'calendarId' => $this->calendarId,
		];
	}

    public function tableSerialize(IL10N $l10n, ?array $params = null): array {
		return [
			'Id' => $this->id,
			'Name' => $this->name,
			'Type' => $l10n->t("Calendar"),
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
