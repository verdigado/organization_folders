<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\Criterion;

class AlwaysUnsatisfiedCriterion extends Criterion {
	public function __construct(
		private string $reason
	) {}

	public function evaluate(bool $allReasons = false, array $criterionTypeBlocklist = []): CriterionResult {
		return new CriterionUnsatisfied([$this->reason]);
	}
}