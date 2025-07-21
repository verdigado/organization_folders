<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IGroupManager;
use \OCP\IGroup;

use OCA\OrganizationFolders\Enum\PrincipalType;

class GroupPrincipal extends PrincipalBackedByGroup {
	private ?IGroup $group;

	public function __construct(
		IGroupManager $groupManager,
		private string $id,
	) {
		parent::__construct($groupManager);

		try {
			$this->group = $this->groupManager->get($id);
			$this->valid = !is_null($this->group);
		} catch (\Exception $e) {
			$this->valid = false;
		}
	}

	public function getType(): PrincipalType {
		return PrincipalType::GROUP;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getFriendlyName(): string {
		return $this->group?->getDisplayName() ?? $this->getId();
	}

	public function getBackingGroupId(): string {
		return $this->getId();
	}

	public function getBackingGroup(): ?IGroup {
		return $this->group;
	}
}