<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion;

use OCP\IL10N;

use OCA\OrganizationFolders\Model\Criterion\MemoizedSimpleCriterion;
use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\OrganizationMemberPrincipal;
use OCA\OrganizationFolders\Model\OrganizationRolePrincipal;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\UserPrincipal;

class GlobalAdminCriterion extends MemoizedSimpleCriterion {

	const string CRITERION_TYPE = "builtin:principalIsGlobalAdmin";


	public function __construct(
		private readonly IL10N $l10n,
		private Principal $principal,
		private array &$principalScratchpad,
	) {}

	protected function evaluateSimpleOnce(): array {
		// TODO: Add admin delegation support

		if(!isset($this->principalScratchpad["principalsIsContainedIn"])) {
			$this->principalScratchpad["principalsIsContainedIn"] = $this->principal->getPrincipalsIsContainedIn();
		}

		foreach($this->principalScratchpad["principalsIsContainedIn"] as $principal) {
			if($principal instanceof GroupPrincipal && $principal->getId() === "admin") {
				if($this->principal instanceof UserPrincipal) {
					$l10n = $this->l10n->t("User is a global admin");
				} else if($this->principal instanceof GroupPrincipal) {
					$l10n = $this->l10n->t("Group is a global admin");
				} else if($this->principal instanceof OrganizationRolePrincipal) {
					$l10n = $this->l10n->t("Organization role is a global admin");
				} else if($this->principal instanceof OrganizationMemberPrincipal) {
					$l10n = $this->l10n->t("Organization is a global admin");
				}

				return [true, $l10n ?? ""];
			}
		}

		if($this->principal instanceof UserPrincipal) {
			$l10n = $this->l10n->t("User is not a global admin");
		} else if($this->principal instanceof GroupPrincipal) {
			$l10n = $this->l10n->t("Group is not a global admin");
		} else if($this->principal instanceof OrganizationRolePrincipal) {
			$l10n = $this->l10n->t("Organization role is not a global admin");
		} else if($this->principal instanceof OrganizationMemberPrincipal) {
			$l10n = $this->l10n->t("Organization is not a global admin");
		}

		return [false, $l10n ?? ""];
	}
}