<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion;

use OCP\IL10N;

use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\Criterion\MemoizedSimpleCriterion;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\UserPrincipal;
use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\OrganizationMemberPrincipal;
use OCA\OrganizationFolders\Model\OrganizationRolePrincipal;
use OCA\OrganizationFolders\Model\PrincipalFilter;
use OCA\OrganizationFolders\Service\ResourceMemberService;

class ResourceDirectManagerCriterion extends MemoizedSimpleCriterion {

	const string CRITERION_TYPE = "builtin:principalIsDirectResourceManager";

	public function __construct(
		private readonly IL10N $l10n,
		private readonly ResourceMemberService $resourceMemberService,
		public readonly Principal $principal,
		private array &$principalScratchpad,
		public readonly int $resourceId,
	) {}

	protected function evaluateSimpleOnce(): array {
		if(!isset($this->principalScratchpad["principalsIsContainedIn"])) {
			$this->principalScratchpad["principalsIsContainedIn"] = $this->principal->getPrincipalsIsContainedIn();
		}

		if(!isset($this->principalScratchpad["principalsIsContainedInFilter"])) {
			$this->principalScratchpad["principalsIsContainedInFilter"] = PrincipalFilter::fromPrincipals($this->principalScratchpad["principalsIsContainedIn"]);
		}

		$count = $this->resourceMemberService->count([
			"resourceId" => $this->resourceId,
			"permissionLevel" => [ResourceMemberPermissionLevel::MANAGER],
			'principal' => $this->principalScratchpad["principalsIsContainedInFilter"],
		]);

		if($count >= 1) {
			$result = true;

			if($this->principal instanceof UserPrincipal) {
				$l10n = $this->l10n->t("User is a manager of resource %s", [$this->resourceId]);
			} else if($this->principal instanceof GroupPrincipal) {
				$l10n = $this->l10n->t("Group is a manager of resource %s", [$this->resourceId]);
			} else if($this->principal instanceof OrganizationRolePrincipal) {
				$l10n = $this->l10n->t("Organization role is a manager of resource %s", [$this->resourceId]);
			} else if($this->principal instanceof OrganizationMemberPrincipal) {
				$l10n = $this->l10n->t("Organization is a manager of resource %s", [$this->resourceId]);
			}
		} else {
			$result = false;

			if($this->principal instanceof UserPrincipal) {
				$l10n = $this->l10n->t("User is not a manager of resource ");
			} else if($this->principal instanceof GroupPrincipal) {
				$l10n = $this->l10n->t("Group is not a manager of resource");
			} else if($this->principal instanceof OrganizationRolePrincipal) {
				$l10n = $this->l10n->t("Organization role is not a manager of resource");
			} else if($this->principal instanceof OrganizationMemberPrincipal) {
				$l10n = $this->l10n->t("Organization is not a manager of resource");
			}
		}

		return [$result, $l10n ?? "", ["resourceId" => $this->resourceId]];
	}
}