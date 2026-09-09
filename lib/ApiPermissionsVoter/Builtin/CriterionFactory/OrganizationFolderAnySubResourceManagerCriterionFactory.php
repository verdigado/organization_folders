<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory;

use OCP\IL10N;

use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderAnySubResourceManagerCriterion;
use OCA\OrganizationFolders\Model\Criterion\CriterionFactory;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Service\ResourceMemberService;

class OrganizationFolderAnySubResourceManagerCriterionFactory extends CriterionFactory {

	/** @var array<string, array<int, OrganizationFolderAnySubResourceManagerCriterion>> */
	private array $cache = [];

	public function __construct(
		private readonly IL10N $l10n,
		private readonly ResourceMemberService $resourceMemberService,
		private array &$scratchpad,
	) {}

	public function build(Principal $principal, int $organizationFolderId): OrganizationFolderAnySubResourceManagerCriterion {
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
		
		return $this->cache[$principalKey][$organizationFolderId] = new OrganizationFolderAnySubResourceManagerCriterion(
			$this->l10n,
			$this->resourceMemberService,
			$principal,
			$principalScratchpad,
			$organizationFolderId,
		);
	}
}