<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\Criterion;

abstract class CriterionResult {
	public function __construct(
		private readonly array $reasons
	) {}

	/**
	 * @psalm-return non-empty-array<int, CriterionResultReason>
	 */
	public function getReasons(): array {
		return $this->reasons;
	}
}