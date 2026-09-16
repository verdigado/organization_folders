<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\Criterion;

/**
 * Utility class, that automatically memoizes child class criterion
 */
abstract class MemoizedSimpleCriterion extends SimpleCriterion {

	private ?array $result = null;

	/**
	 * @return list{bool, ?string, ?array} satisfied/unsatisfied, l10n string, details
	 */
	abstract protected function evaluateSimpleOnce(): array;

	/**
	 * @inheritDoc
	 */
	final public function evaluateSimple(): array {
		if($this->result === null) {
			return $this->result = $this->evaluateSimpleOnce();
		} else {
			return $this->result;
		}
	}
}