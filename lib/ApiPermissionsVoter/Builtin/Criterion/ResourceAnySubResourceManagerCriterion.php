<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion;

use OCP\IL10N;

use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\ResourceAnySubResourceManagerCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\ResourceDirectManagerCriterionFactory;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Model\Criterion\AnyCriterion;
use OCA\OrganizationFolders\Model\Criterion\Criterion;
use OCA\OrganizationFolders\Model\Criterion\CompositeCriterion;
use OCA\OrganizationFolders\Model\Criterion\CriterionResult;
use OCA\OrganizationFolders\Model\Criterion\CriterionResultReason;
use OCA\OrganizationFolders\Model\Criterion\CriterionSatisfied;
use OCA\OrganizationFolders\Model\Criterion\CriterionUnsatisfied;
use OCA\OrganizationFolders\Model\Principal;
use OCA\OrganizationFolders\Model\UserPrincipal;
use OCA\OrganizationFolders\Model\GroupPrincipal;
use OCA\OrganizationFolders\Model\OrganizationMemberPrincipal;
use OCA\OrganizationFolders\Model\OrganizationRolePrincipal;
use OCA\OrganizationFolders\Service\ResourceService;

class ResourceAnySubResourceManagerCriterion extends CompositeCriterion {
	const string CRITERION_TYPE = "builtin:principalIsSubResourceManager";

	private ?Criterion $calculatedCriterion = null;

	public function __construct(
		private readonly IL10N $l10n,
		private readonly ResourceService $resourceService,
		private Principal $principal,
		private Resource $resource,
		private array &$scratchpad,
	) {}

	public function evaluate(bool $allReasons = false, array $criterionTypeBlocklist = []): CriterionResult {
		if(array_key_exists(static::CRITERION_TYPE, $criterionTypeBlocklist)) {
			return new CriterionUnsatisfied([new CriterionResultReason("!" . static::CRITERION_TYPE, "Blocked by Blocklist")]);
		}
		
		if($this->calculatedCriterion === null) {
			$subresources = $this->resourceService->findAll($this->resource->getOrganizationFolderId(), $this->resource->getId());

			/** @var ResourceDirectManagerCriterionFactory */
			$directManagerCriterionFactory = $this->scratchpad["criterionFactories"][ResourceDirectManagerCriterion::CRITERION_TYPE];

			/** @var ResourceAnySubResourceManagerCriterionFactory */
			$selfFactory = $this->scratchpad["criterionFactories"][self::CRITERION_TYPE];

			$directCriterions = [];
			$recursiveCriterions = [];

			foreach($subresources as $subresource) {
				$directCriterions[] = $directManagerCriterionFactory->build($this->principal, $subresource->getId());
				$recursiveCriterions[] = $selfFactory->build($this->principal, $subresource);
			}

			$this->calculatedCriterion = new AnyCriterion([...$directCriterions, ...$recursiveCriterions]);
		}

		$evalResult = $this->calculatedCriterion->evaluate($allReasons);

		if($evalResult instanceof CriterionSatisfied) {
			$wrappedReasons = [];

			foreach($evalResult->getReasons() as $reason) {
				if($reason->type === ResourceDirectManagerCriterion::CRITERION_TYPE) {
					// TODO: For l10n we should use resource name, not ID
					$l10nparams = [$reason->details["resourceId"] ?? "?"];

					if($this->principal instanceof UserPrincipal) {
						$l10n = $this->l10n->t("User is a manager of subresource %s", $l10nparams);
					} else if($this->principal instanceof GroupPrincipal) {
						$l10n = $this->l10n->t("Group is a manager of subresource %s", $l10nparams);
					} else if($this->principal instanceof OrganizationRolePrincipal) {
						$l10n = $this->l10n->t("Organization role is a manager of subresource %s", $l10nparams);
					} else if($this->principal instanceof OrganizationMemberPrincipal) {
						$l10n = $this->l10n->t("Organization is a manager of subresource %s", $l10nparams);
					}

					$wrappedReasons[] = new CriterionResultReason(static::CRITERION_TYPE, $l10n ?? "", ["subresourceId" => $reason->details["resourceId"] ?? null]);
				}
			}

			return new CriterionSatisfied($wrappedReasons);
		} else {
			if($this->principal instanceof UserPrincipal) {
				$l10n = $this->l10n->t("User is not a manager of any subresource");
			} else if($this->principal instanceof GroupPrincipal) {
				$l10n = $this->l10n->t("Group is not a manager of any subresource");
			} else if($this->principal instanceof OrganizationRolePrincipal) {
				$l10n = $this->l10n->t("Organization role is not a manager of any subresource");
			} else if($this->principal instanceof OrganizationMemberPrincipal) {
				$l10n = $this->l10n->t("Organization is not a manager of any subresource");
			}

			return new CriterionUnsatisfied([new CriterionResultReason("!" . static::CRITERION_TYPE, $l10n ?? "")]);
		}
	}
}