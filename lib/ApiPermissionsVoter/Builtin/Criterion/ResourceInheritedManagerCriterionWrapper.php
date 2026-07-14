<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion;

use OCP\IL10N;

use OCA\OrganizationFolders\Model\Criterion\Criterion;
use OCA\OrganizationFolders\Model\Criterion\CriterionResult;
use OCA\OrganizationFolders\Model\Criterion\CriterionResultReason;
use OCA\OrganizationFolders\Model\Criterion\CriterionSatisfied;
use OCA\OrganizationFolders\Model\Criterion\CriterionUnsatisfied;
use OCA\OrganizationFolders\Model\UserPrincipal;
use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\OrganizationMemberPrincipal;
use OCA\OrganizationFolders\Model\OrganizationRolePrincipal;

class ResourceInheritedManagerCriterionWrapper extends Criterion {

	const string CRITERION_TYPE = "builtin:principalIsInheritedResourceManager";

	public function __construct(
		private readonly IL10N $l10n,
		private readonly ResourceManagerCriterion|OrganizationFolderManagerCriterion $criterion,
	) {}

	public function evaluate(bool $allReasons = false, array $criterionTypeBlocklist = []): CriterionResult {
		if(array_key_exists(static::CRITERION_TYPE, $criterionTypeBlocklist)) {
			return new CriterionUnsatisfied([new CriterionResultReason("!" . static::CRITERION_TYPE, "Blocked by Blocklist")]);
		}

		// TODO: investigate wether memoization could yield relevant performance improvement (probably not)
		
		$evalResult = $this->criterion->evaluate($allReasons);

		$principal = $this->criterion->principal;

		if($evalResult instanceof CriterionSatisfied) {
			$wrappedReasons = [];

			foreach($evalResult->getReasons() as $reason) {
				if($reason->type === ResourceDirectManagerCriterion::CRITERION_TYPE) {
					// TODO: For l10n we should use resource name, not ID
					$l10nparams = [$reason->details["resourceId"] ?? "?"];

					if($principal instanceof UserPrincipal) {
						$l10n = $this->l10n->t("User is an inherited manager from resource %s", $l10nparams);
					} else if($principal instanceof GroupPrincipal) {
						$l10n = $this->l10n->t("Group is an inherited manager from resource %s", $l10nparams);
					} else if($principal instanceof OrganizationRolePrincipal) {
						$l10n = $this->l10n->t("Organization role is an inherited manager from resource %s", $l10nparams);
					} else if($principal instanceof OrganizationMemberPrincipal) {
						$l10n = $this->l10n->t("Organization is an inherited manager from resource %s", $l10nparams);
					}

					$wrappedReasons[] = new CriterionResultReason(static::CRITERION_TYPE, $l10n ?? "", ["inheritedFrom" => $reason->details]);
				} else if($reason->type === OrganizationFolderManagerCriterion::CRITERION_TYPE) {
					// TODO: For l10n we should use resource name, not ID
					$l10nparams = [$reason->details["organizationFolderId"] ?? "?"];

					if($principal instanceof UserPrincipal) {
						$l10n = $this->l10n->t("User is an inherited manager from orgaization folder %s", $l10nparams);
					} else if($principal instanceof GroupPrincipal) {
						$l10n = $this->l10n->t("Group is an inherited manager from orgaization folder %s", $l10nparams);
					} else if($principal instanceof OrganizationRolePrincipal) {
						$l10n = $this->l10n->t("Organization role is an inherited manager from orgaization folder %s", $l10nparams);
					} else if($principal instanceof OrganizationMemberPrincipal) {
						$l10n = $this->l10n->t("Organization is an inherited manager from orgaization folder %s", $l10nparams);
					}

					$wrappedReasons[] = new CriterionResultReason(static::CRITERION_TYPE, $l10n ?? "", ["inheritedFrom" => $reason->details]);
				} else {
					$wrappedReasons[] = $reason;
				}
			}

			return new CriterionSatisfied($wrappedReasons);
		} else {
			if($principal instanceof UserPrincipal) {
				$l10n = $this->l10n->t("User is not an inherited manager");
			} else if($principal instanceof GroupPrincipal) {
				$l10n = $this->l10n->t("Group is not an inherited manager");
			} else if($principal instanceof OrganizationRolePrincipal) {
				$l10n = $this->l10n->t("Organization role is not an inherited manager");
			} else if($principal instanceof OrganizationMemberPrincipal) {
				$l10n = $this->l10n->t("Organization is not an inherited manager");
			}

			return new CriterionUnsatisfied([new CriterionResultReason("!" . static::CRITERION_TYPE, $l10n ?? "")]);
		}
	}
}