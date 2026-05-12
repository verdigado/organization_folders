<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use \JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

use OCP\IL10N;

class OrganizationFolder implements JsonSerializable, TableSerializable {
	public function __construct(
		private readonly int $id,
		private string $name,
		private int $quota,
		private readonly int $storageId,
		private readonly int $rootNodeFileId,
		private ?string $organizationProvider = null,
		private ?int $organizationId = null,
		private ?string $serviceAccountUid = null,
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

	public function getStorageId(): int {
		return $this->storageId;
	}

	public function getRootNodeFileId(): int {
		return $this->rootNodeFileId;
	}

	public function getOrganizationProviderId(): ?string {
		return $this->organizationProvider;
	}

	public function getOrganizationId(): ?int {
		return $this->organizationId;
	}

	public function getServiceAccountUid(): ?string {
		return $this->serviceAccountUid;
	}

	/**
	 * @return string[]
	 */
	public function getEnabledResourceTypes(): array {
		// TODO: currently all available types are enabled, add configuration options to organization folders for this

		if(is_null($this->serviceAccountUid)) {
			return ["folder"];
		}

		return ["folder", "calendar"];
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'quota' => $this->quota,
			'organizationProviderId' => $this->organizationProvider,
			'organizationId' => $this->organizationId,
			'serviceAccountUid' => $this->serviceAccountUid,
			'enabledResourceTypes' => $this->getEnabledResourceTypes(),
		];
	}

	public function limitedJsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'quota' => $this->quota,
			'organizationProviderId' => $this->organizationProvider,
			'organizationId' => $this->organizationId,
			'enabledResourceTypes' => $this->getEnabledResourceTypes(),
		];
	}

	public function tableSerialize(IL10N $l10n, ?array $params = null): array {
		return [
			'Id' => $this->id,
			'Name' => $this->name,
			'Quota' => $this->quota,
			'Organization Provider ID' => $this->organizationProvider,
			'Organization ID' => $this->organizationId,
			'Service Account UID' => $this->serviceAccountUid,
		];
	}
}