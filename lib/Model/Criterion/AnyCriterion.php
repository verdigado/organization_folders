<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\Criterion;

class AnyCriterion extends CompositeCriterion {

	private ?CriterionResult $memoizedResult = null;
	private bool $memoizedAllReasons = false;

	/**
	 * @param Criterion[] $operands
	 */
	public function __construct(
		public readonly array $operands,
	) {}

	public function evaluate(bool $allReasons = false, array $criterionTypeBlocklist = []): CriterionResult {
		$blocklistInactive = count($criterionTypeBlocklist) === 0;

		if($allReasons) {
			if($blocklistInactive && $this->memoizedResult !== null && $this->memoizedAllReasons) {
				return $this->memoizedResult;
			}

			// evaluate all operands
			$satisfiedReasons = [];
			$unsatisfiedReasons = [];

			foreach($this->operands as $operand) {
				$result = $operand->evaluate($allReasons, $criterionTypeBlocklist);

				if($result instanceof CriterionSatisfied) {
					foreach($result->getReasons() as $reason) {
						$satisfiedReasons[] = $reason;
					}
				} else if ($result instanceof CriterionUnsatisfied) {
					foreach($result->getReasons() as $reason) {
						$unsatisfiedReasons[] = $reason;
					}
				}
			}

			if(count($satisfiedReasons) > 0) {
				$result = new CriterionSatisfied($satisfiedReasons);
			} else {
				$result = new CriterionUnsatisfied($unsatisfiedReasons);
			}

			if($blocklistInactive) {
				$this->memoizedResult = $result;
				$this->memoizedAllReasons = true;
			}
			
			return $result;
		} else {
			if($blocklistInactive && $this->memoizedResult !== null) {
				return $this->memoizedResult;
			}

			// return after first satisfied operand
			$unsatisfiedReasons = [];

			foreach($this->operands as $operand) {
				$evalResult = $operand->evaluate($allReasons, $criterionTypeBlocklist);

				if($evalResult instanceof CriterionSatisfied) {
					$result = $evalResult;
					
					if($blocklistInactive) {
						$this->memoizedResult = $result;
					}

					return $result;
				} else if ($evalResult instanceof CriterionUnsatisfied) {
					foreach($evalResult->getReasons() as $reason) {
						$unsatisfiedReasons[] = $reason;
					}
				}
			}

			$result = new CriterionUnsatisfied($unsatisfiedReasons);

			if($blocklistInactive) {
				$this->memoizedResult = $result;
			}

			return $result;
		}
	}
}