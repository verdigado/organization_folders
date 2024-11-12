<?php

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Enum\PrincipalType;

class Principal implements \JsonSerializable {
    public function __construct(
		private PrincipalType $type,
        private string $id,
	) {
        // check if id fits format
    }

    public function getType(): PrincipalType {
		return $this->type;
	}

    public function getId(): string {
		return $this->id;
	}

	public function jsonSerialize(): array {
		return [
			'type' => $this->type->value,
			'id' => $this->id,
		];
	}
}