<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion;

use OCP\IL10N;

use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\OrganizationFolderManagerCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\ResourceDirectManagerCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\ResourceManagerCriterionFactory;
use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Model\Criterion\AnyCriterion;
use OCA\OrganizationFolders\Model\Criterion\Criterion;
use OCA\OrganizationFolders\Model\Criterion\CriterionResult;
use OCA\OrganizationFolders\Model\Principal;

class ResourceManagerCriterion extends Criterion {
	const string CRITERION_TYPE = "builtin:principalIsResourceManager";

	private ?Criterion $calculatedCriterion = null;

	public function __construct(
		private readonly IL10N $l10n,
		public readonly Principal $principal,
		public readonly Resource $resource,
		private array &$scratchpad,
	) {}

	public function evaluate(bool $allReasons = false, array $criterionTypeBlocklist = []): CriterionResult {
		if($this->calculatedCriterion === null) {
			/** @var ResourceDirectManagerCriterionFactory */
			$directManagerCriterionFactory = $this->scratchpad["criterionFactories"][ResourceDirectManagerCriterion::CRITERION_TYPE];
			$directManagerCriterion = $directManagerCriterionFactory->build($this->principal, $this->resource->getId());

			if($this->resource->getInheritManagers()) {
				// inheriting manager permissions from level above
				if($this->resource->getParentResourceId() !== null) {
					// not top-level resource -> allowed to manage resource if allowed to manage parent resource

					/** @var ResourceManagerCriterionFactory */
					$selfFactory = $this->scratchpad["criterionFactories"][self::CRITERION_TYPE];
					$parentResourceManagerCriterion = $selfFactory->buildFromId($this->principal, $this->resource->getParentResourceId());
					$wrappedParentResourceManagerCriterion = new ResourceInheritedManagerCriterionWrapper($this->l10n, $parentResourceManagerCriterion);

					$this->calculatedCriterion = new AnyCriterion([$directManagerCriterion, $wrappedParentResourceManagerCriterion]);
				} else {
					// top-level resource -> allowed to manage resource if manager of organization folder

					/** @var OrganizationFolderManagerCriterionFactory */
					$organizationFolderManagerCriterionFactory = $this->scratchpad["criterionFactories"][OrganizationFolderManagerCriterion::CRITERION_TYPE];
					$organizationFolderManagerCriterion = $organizationFolderManagerCriterionFactory->build($this->principal, $this->resource->getOrganizationFolderId());
					$wrappedOrganizationFolderManagerCriterion = new ResourceInheritedManagerCriterionWrapper($this->l10n, $organizationFolderManagerCriterion);

					$this->calculatedCriterion = new AnyCriterion([$directManagerCriterion, $wrappedOrganizationFolderManagerCriterion]);
				}
			} else {
				// no inheritance
				$this->calculatedCriterion = $directManagerCriterion;
			}
		}

		return $this->calculatedCriterion->evaluate($allReasons, $criterionTypeBlocklist);
	}
}