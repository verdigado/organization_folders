<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory;

use OCP\IL10N;

use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderAdminCriterion;
use OCA\OrganizationFolders\Model\Criterion\CriterionFactory;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;

class OrganizationFolderAdminCriterionFactory extends CriterionFactory {

	/** @var array<string, array<int, OrganizationFolderAdminCriterion>> */
	private array $cache = [];

	public function __construct(
		private readonly IL10N $l10n,
		private readonly OrganizationFolderMemberService $organizationFolderMemberService,
		private array &$scratchpad,
	) {}

	public function build(Principal $principal, int $organizationFolderId): OrganizationFolderAdminCriterion {
		$principalKey = $principal->getKey();

		if(isset($this->cache[$principalKey])){
			if(isset($this->cache[$principalKey][$organizationFolderId])) {
				return $this->cache[$principalKey][$organizationFolderId];
			}
		} else {
			$this->cache[$principalKey] = [];
		}

		if(!isset($this->scratchpad["principal"])) {
			$this->scratchpad["principal"] = [];
		}

		if(!isset($this->scratchpad["principal"][$principalKey])) {
			$this->scratchpad["principal"][$principalKey] = [];
		}

		$principalScratchpad = &$this->scratchpad["principal"][$principalKey];
		
		return $this->cache[$principalKey][$organizationFolderId] = new OrganizationFolderAdminCriterion(
			$this->l10n,
			$this->organizationFolderMemberService,
			$principal,
			$principalScratchpad,
			$organizationFolderId,
		);
	}
}