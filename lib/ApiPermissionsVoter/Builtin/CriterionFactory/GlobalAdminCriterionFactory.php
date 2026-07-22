<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory;

use OCP\IL10N;

use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\GlobalAdminCriterion;
use OCA\OrganizationFolders\Model\Criterion\CriterionFactory;
use OCA\OrganizationFolders\Model\Principal;

class GlobalAdminCriterionFactory extends CriterionFactory {

	/** @var array<string, GlobalAdminCriterion> */
	private array $cache = [];

	public function __construct(
		private readonly IL10N $l10n,
		private array &$scratchpad,
	) {}

	public function build(Principal $principal): GlobalAdminCriterion {
		$principalKey = $principal->getKey();

		if(isset($this->cache[$principalKey])){
			return $this->cache[$principalKey];
		}

		if(!isset($this->scratchpad["principal"])) {
			$this->scratchpad["principal"] = [];
		}

		if(!isset($this->scratchpad["principal"][$principalKey])) {
			$this->scratchpad["principal"][$principalKey] = [];
		}

		$principalScratchpad = &$this->scratchpad["principal"][$principalKey];
		
		return new GlobalAdminCriterion(
			$this->l10n,
			$principal,
			$principalScratchpad,
		);
	}
}