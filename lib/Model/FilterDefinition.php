<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Enum\FilterType;

/**
 * Describes one filter a provider accepts, so the frontend can render a matching input
 */
class FilterDefinition implements \JsonSerializable {
	/**
	 * @param string $name key under which the filter value is passed back
	 * @param FilterOption[] $options only used for FilterType::SELECT
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $label,
		public readonly FilterType $type,
		public readonly array $options = [],
	) {
	}

	public function jsonSerialize(): array {
		$result = [
			'name' => $this->name,
			'label' => $this->label,
			'type' => $this->type->value,
		];

		if($this->type === FilterType::SELECT) {
			$result['options'] = $this->options;
		}

		return $result;
	}
}
