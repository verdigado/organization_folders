<?php

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Interface\TableSerializable;

class Organization implements \JsonSerializable, TableSerializable {
    public function __construct(
		private int $id,
		private string $friendlyName,
        private string $membersGroup,
	) {
    }

    public function getId(): int {
		return $this->id;
	}

    public function getFriendlyName(): string {
		return $this->friendlyName;
	}

    public function getMembersGroup(): string {
		return $this->membersGroup;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'friendlyName' => $this->friendlyName,
			'membersGroup' => $this->membersGroup,
		];
	}

	public function tableSerialize(?array $params = null): array {
		return [
			'Id' => $this->id,
			'Friendly Name' => $this->friendlyName,
			'Members Group' => $this->membersGroup,
		];
	}
}