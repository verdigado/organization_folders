<?php

namespace OCA\OrganizationFolders\Model;

class Organization implements \JsonSerializable {
    public function __construct(
		private int $id,
        private string $membersGroup,
	) {
    }

    public function getId(): int {
		return $this->id;
	}

    public function getMembersGroup(): string {
		return $this->membersGroup;
	}
}