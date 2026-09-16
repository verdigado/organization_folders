<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\Criterion;

class CriterionResultReason {
	public function __construct(
		public readonly string $type,
		public readonly string $l10n,
		public readonly ?array $details = null,
	) {}
}