<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Interface\TableSerializable;

class Organization implements \JsonSerializable, TableSerializable {
	public function __construct(
		private int $id,
		private string $friendlyName,
		private string $membersGroup,
		private ?int $parentOrganizationId = null,
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

	public function getParentOrganizationId(): ?int {
		return $this->parentOrganizationId;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'friendlyName' => $this->friendlyName,
			'membersGroup' => $this->membersGroup,
			'parentOrganizationId' => $this->parentOrganizationId,
		];
	}

	public function tableSerialize(?array $params = null): array {
		return [
			'Id' => $this->id,
			'Friendly Name' => $this->friendlyName,
			'Members Group' => $this->membersGroup,
			'Parent Organization Id' => $this->parentOrganizationId,
		];
	}
}