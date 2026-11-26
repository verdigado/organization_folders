<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

class FilterOption implements \JsonSerializable {
	public function __construct(
		public readonly string $value,
		public readonly string $label,
	) {
	}

	public function jsonSerialize(): array {
		return [
			'value' => $this->value,
			'label' => $this->label,
		];
	}
}
