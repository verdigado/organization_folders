<?php

namespace OCA\OrganizationFolders\Model;

use \JsonSerializable;
use OCA\OrganizationFolders\Interface\TableSerializable;

class OrganizationFolder implements JsonSerializable, TableSerializable {
    public function __construct(
		private int $id,
        private string $name,
        private int $quota,
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

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
            'quota' => $this->quota,
		];
	}

	public function tableSerialize(): array {
		return [
			'Id' => $this->id,
			'Name' => $this->name,
            'Quota' => $this->quota,
		];
	}
}