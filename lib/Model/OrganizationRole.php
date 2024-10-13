<?php

namespace OCA\OrganizationFolders\Model;

class OrganizationRole implements \JsonSerializable {
    public function __construct(
		private int $id,
        private int $organizationId,
        private string $friendlyName,
        private string $membersGroup,
	) {
    }

    public function getId(): int {
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
}