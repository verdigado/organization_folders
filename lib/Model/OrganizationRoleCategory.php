<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

class OrganizationRoleCategory implements \JsonSerializable {
	public function __construct(
		private string $id,
		private string $friendlyName,
	) {
	}

	public function getId(): string {
		return $this->id;
	}

	public function getFriendlyName(): string {
		return $this->friendlyName;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'friendlyName' => $this->friendlyName,
		];
	}
}