<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ApiAuthorization;

use OCA\OrganizationFolders\Model\Criterion\CriterionResult;
use OCA\OrganizationFolders\Model\Criterion\CriterionResultReason;
use OCA\OrganizationFolders\Model\Criterion\CriterionSatisfied;

abstract class ApiAuthorizationDecision implements \JsonSerializable {
	public function __construct(
		private readonly array $reasons,
		private readonly bool $reasonsExhaustive,
	) {}

	public static function fromCriterionResult(CriterionResult $criterionResult, bool $reasonsExhaustive): ApiAuthorizationDecision {
		if($criterionResult instanceof CriterionSatisfied) {
			return new ApiAuthorizationGranted($criterionResult->getReasons(), $reasonsExhaustive);
		} else {
			return new ApiAuthorizationDenied($criterionResult->getReasons(), $reasonsExhaustive);
		}
	}

	/**
	 * @psalm-return non-empty-array<int, CriterionResultReason>
	 */
	public function getReasons(): array {
		return $this->reasons;
	}

	public function getReasonsExhaustive(): bool {
		return $this->reasonsExhaustive;
	}

	abstract public function jsonSerialize(): array;
}