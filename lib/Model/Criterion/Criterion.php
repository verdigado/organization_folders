<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\Criterion;

abstract class Criterion {
	/**
	 * Evaluate criterion, return either CriterionSatisfied or CriterionUnsatisfied
	 * @param bool $allReasons if true disable evaluation optimizations that result in not all reasons being returned
	 * @param array<string, mixed> $criterionTypeBlocklist associative array used as a set (values are ignored); criterions of the given types will force-evaluate to CriterionUnsatisfied
	 * @return CriterionResult
	 */
	abstract public function evaluate(bool $allReasons = false, array $criterionTypeBlocklist = []): CriterionResult;
}