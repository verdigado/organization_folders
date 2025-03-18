<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Interface\TableSerializable;

class OrganizationRole implements \JsonSerializable, TableSerializable {
	public function __construct(
		private string $id,
		private int $organizationId,
		private string $friendlyName,
		private string $membersGroup,
	) {
	}

	public function getId(): string {
		return $this->id;
	}

	public function getOrganizationId(): int {
		return $this->organizationId;
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
			'organizationId' => $this->organizationId,
			'friendlyName' => $this->friendlyName,
			'membersGroup' => $this->membersGroup,
		];
	}

  public function tableSerialize(?array $params = null): array {
		return [
			'Id' => $this->id,
			'Name' => $this->friendlyName,
			'Organization Id' => $this->organizationId,
			'Members Group' => $this->membersGroup,
		];
	}
}