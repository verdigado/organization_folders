<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use \JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

class OrganizationFolder implements JsonSerializable, TableSerializable {
	public function __construct(
		private int $id,
		private string $name,
		private int $quota,
		private ?string $organizationProvider = null,
		private ?int $organizationId = null,
	) {
	}

	public function getId(): int {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getQuota(): int {
		return $this->quota;
	}

	public function getOrganizationProvider(): ?string {
		return $this->organizationProvider;
	}

	public function getOrganizationId(): ?int {
		return $this->organizationId;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'quota' => $this->quota,
			'organizationProviderId' => $this->organizationProvider,
			'organizationId' => $this->organizationId,
		];
	}

	// currently no different than non-limited
	public function limitedJsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'quota' => $this->quota,
			'organizationProviderId' => $this->organizationProvider,
			'organizationId' => $this->organizationId,
		];
	}

	public function tableSerialize(?array $params = null): array {
		return [
			'Id' => $this->id,
			'Name' => $this->name,
			'Quota' => $this->quota,
			'Organization Provider' => $this->organizationProvider,
			'Organization Id' => $this->organizationId,
		];
	}
}