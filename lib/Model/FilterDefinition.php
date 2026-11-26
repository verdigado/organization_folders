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
	 * @param bool $optionsComplete false if $options is only a subset and the rest must be fetched via the options search
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $label,
		public readonly FilterType $type,
		public readonly array $options = [],
		public readonly bool $optionsComplete = true,
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
			$result['optionsComplete'] = $this->optionsComplete;
		}

		return $result;
	}
}
