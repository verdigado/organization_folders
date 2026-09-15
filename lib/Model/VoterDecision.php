<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Model\Criterion\Criterion;

class VoterDecision {
	/**
	 * @param Criterion $criterion
	 * @param bool $final true ensures voter has the final say for this (principal, subject, action) combination
	 */
	public function __construct(
		public readonly Criterion $criterion,
		public readonly bool $final,
	) {}
}