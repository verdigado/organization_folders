<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IGroupManager;
use \OCP\IGroup;

use OCA\OrganizationFolders\Enum\PrincipalType;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class GroupPrincipal extends PrincipalBackedByGroup {
	private bool $valid;

	private bool $initialized = false;

	public function __construct(
		PrincipalFactory $factory,
		IGroupManager $groupManager,
		OrganizationProviderManager $organizationProviderManager,
		private readonly string $id,
		bool $lazy = true,
		private ?IGroup $group = null,
	) {
		parent::__construct($factory, $groupManager, $organizationProviderManager);

		if($this->group === null) {
			// IGroup not provided to constructor, looking it up by id
			if(!$lazy) {
				$this->init();
			}
		} else {
			// IUser provided to constructor
			$this->valid = true;
			$this->initialized = true;
		}
	}

	private function init(): void {
		try {
			$this->group = $this->groupManager->get($this->id);
			$this->valid = $this->group !== null;
		} catch (\Exception $e) {
			$this->valid = false;
		}
		$this->initialized = true;
	}

	public function getType(): PrincipalType {
		return PrincipalType::GROUP;
	}

	public function getId(): string {
		return $this->id;
	}

	public function isValid(): bool {
		if(!$this->initialized) {
			$this->init();
		}

		return $this->valid;
	}

	public function getFriendlyName(): string {
		if(!$this->initialized) {
			$this->init();
		}

		return $this->group?->getDisplayName() ?? $this->getId();
	}

	public function getBackingGroupId(): string {
		return $this->id;
	}

	public function getBackingGroup(): ?IGroup {
		if(!$this->initialized) {
			$this->init();
		}

		return $this->group;
	}
}