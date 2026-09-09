<?php

namespace OCA\OrganizationFolders\ApiPermissionsVoter\Builtin;

use OCP\IL10N;

use OCA\OrganizationFolders\ApiPermissionsVoter\ApiPermissionsVoter;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\GlobalAdminCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\GlobalAdminCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderAdminCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\OrganizationFolderAdminCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderAnySubResourceManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\OrganizationFolderAnySubResourceManagerCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\OrganizationFolderManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\OrganizationFolderManagerCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\ResourceAnySubResourceManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\ResourceAnySubResourceManagerCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\ResourceDirectManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\ResourceDirectManagerCriterionFactory;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\Criterion\ResourceManagerCriterion;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\CriterionFactory\ResourceManagerCriterionFactory;
use OCA\OrganizationFolders\Service\OrganizationFolderMemberService;
use OCA\OrganizationFolders\Service\ResourceMemberService;
use OCA\OrganizationFolders\Service\ResourceService;

abstract class BuiltinVoter extends ApiPermissionsVoter {

	public function __construct(
		protected readonly IL10N $l10n,
		protected readonly OrganizationFolderMemberService $organizationFolderMemberService,
		protected readonly ResourceService $resourceService,
		protected readonly ResourceMemberService $resourceMemberService,
	) {}

	protected function registerCriterionFactories(array &$scratchpad): void {
		if(!isset($scratchpad["criterionFactories"])) {
			$scratchpad["criterionFactories"] = [];
		}

		$criterionFactories = &$scratchpad["criterionFactories"];

		if(!isset($criterionFactories[GlobalAdminCriterion::CRITERION_TYPE])) {
			$criterionFactories[GlobalAdminCriterion::CRITERION_TYPE] = new GlobalAdminCriterionFactory(
				$this->l10n,
				$scratchpad,
			);
		}

		if(!isset($criterionFactories[OrganizationFolderAdminCriterion::CRITERION_TYPE])) {
			$criterionFactories[OrganizationFolderAdminCriterion::CRITERION_TYPE] = new OrganizationFolderAdminCriterionFactory(
				$this->l10n,
				$this->organizationFolderMemberService,
				$scratchpad,
			);
		}

		if(!isset($criterionFactories[OrganizationFolderManagerCriterion::CRITERION_TYPE])) {
			$criterionFactories[OrganizationFolderManagerCriterion::CRITERION_TYPE] = new OrganizationFolderManagerCriterionFactory(
				$this->l10n,
				$this->organizationFolderMemberService,
				$scratchpad,
			);
		}

		if(!isset($criterionFactories[OrganizationFolderAnySubResourceManagerCriterion::CRITERION_TYPE])) {
			$criterionFactories[OrganizationFolderAnySubResourceManagerCriterion::CRITERION_TYPE] = new OrganizationFolderAnySubResourceManagerCriterionFactory(
				$this->l10n,
				$this->resourceMemberService,
				$scratchpad,
			);
		}

		if(!isset($criterionFactories[ResourceDirectManagerCriterion::CRITERION_TYPE])) {
			$criterionFactories[ResourceDirectManagerCriterion::CRITERION_TYPE] = new ResourceDirectManagerCriterionFactory(
				$this->l10n,
				$this->resourceMemberService,
				$scratchpad,
			);
		}

		if(!isset($criterionFactories[ResourceManagerCriterion::CRITERION_TYPE])) {
			$criterionFactories[ResourceManagerCriterion::CRITERION_TYPE] = new ResourceManagerCriterionFactory(
				$this->l10n,
				$this->resourceService,
				$scratchpad,
			);
		}

		if(!isset($criterionFactories[ResourceAnySubResourceManagerCriterion::CRITERION_TYPE])) {
			$criterionFactories[ResourceAnySubResourceManagerCriterion::CRITERION_TYPE] = new ResourceAnySubResourceManagerCriterionFactory(
				$this->l10n,
				$this->resourceService,
				$scratchpad,
			);
		}
	}
}