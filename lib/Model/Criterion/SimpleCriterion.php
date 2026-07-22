<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\Criterion;

/**
 * A simple criterion is a criterion with a specific type unlike a CompositeCriterion, which evaluates multiple criteria types
 */
abstract class SimpleCriterion extends Criterion {

	const string CRITERION_TYPE = "";

	/**
	 * @return list{bool, ?string, ?array} satisfied/unsatisfied, l10n string, details
	 */
	abstract protected function evaluateSimple(): array;

	/**
	 * @inheritDoc
	 * @param bool $allReasons ignored as simple criteria only have one reason
	 */
	final public function evaluate(bool $allReasons = false, array $criterionTypeBlocklist = []): CriterionResult {
		if(array_key_exists(static::CRITERION_TYPE, $criterionTypeBlocklist)) {
			return new CriterionUnsatisfied([new CriterionResultReason("!" . static::CRITERION_TYPE, "Blocked by Blocklist")]);
		}

		$evaluation = $this->evaluateSimple();

		if($evaluation[0]) {
			return new CriterionSatisfied([new CriterionResultReason(static::CRITERION_TYPE, $evaluation[1] ?? null, $evaluation[2] ?? null)]);
		} else {
			return new CriterionUnsatisfied([new CriterionResultReason("!" . static::CRITERION_TYPE, $evaluation[1] ?? null, $evaluation[2] ?? null)]);
		}
	}
}