<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Registry;

use OCP\EventDispatcher\IEventDispatcher;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Events\RegisterApiPermissionsVoterEvent;
use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\ApiPermissionsVoter\ApiPermissionsVoter;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\BuiltinOrganizationFolderVoter;
use OCA\OrganizationFolders\ApiPermissionsVoter\Builtin\BuiltinResourceVoter;

class ApiPermissionsVoterRegistry {
	/**
	 * @var array<string, array<int, list<ApiPermissionsVoter>>>
	 */
	private array $votersBySubjectTypeAndPriority = [];

	/**
	 * @var array<string, bool>
	 */
	private array $sortedBySubjectType = [];

	public function __construct(
		IEventDispatcher $dispatcher,
		BuiltinOrganizationFolderVoter $organizationFolderVoter,
		BuiltinResourceVoter $resourceVoter,
	) {
		// load built-in voters
		$this->votersBySubjectTypeAndPriority[OrganizationFolder::VOTER_SUBJECT_TYPE] = [];
		$this->votersBySubjectTypeAndPriority[OrganizationFolder::VOTER_SUBJECT_TYPE][0] = [$organizationFolderVoter];
		$this->sortedBySubjectType[OrganizationFolder::VOTER_SUBJECT_TYPE] = true;
		$this->votersBySubjectTypeAndPriority[Resource::VOTER_SUBJECT_TYPE] = [];
		$this->votersBySubjectTypeAndPriority[Resource::VOTER_SUBJECT_TYPE][0] = [$resourceVoter];
		$this->sortedBySubjectType[Resource::VOTER_SUBJECT_TYPE] = true;

		// ask other apps
		$event = new RegisterApiPermissionsVoterEvent($this);
		$dispatcher->dispatchTyped($event);
	}

	/**
	 * Returns voters in ascending order of priority
	 * @return ApiPermissionsVoter[]
	 */
	public function getVotersForSubjectType(string $subjectType): array {
		if(!isset($this->votersBySubjectTypeAndPriority[$subjectType])) {
			return [];
		}

		if (!($this->sortedBySubjectType[$subjectType] ?? null)) {
			ksort($this->votersBySubjectTypeAndPriority[$subjectType]);
			$this->sortedBySubjectType[$subjectType] = true;
		}

		return array_merge(...array_values($this->votersBySubjectTypeAndPriority[$subjectType]));
	}

	public function registerVoter(ApiPermissionsVoter $voter, string $subjectType, int $priority): self {
		if(!($priority > 0 && $priority <= 100)) {
			throw new \InvalidArgumentException("Priority must be between 1 and 100");
		}
		
		if(!isset($this->votersBySubjectTypeAndPriority[$subjectType])) {
			$this->votersBySubjectTypeAndPriority[$subjectType] = [];
		}

		if(!isset($this->votersBySubjectTypeAndPriority[$subjectType][$priority])) {
			$this->votersBySubjectTypeAndPriority[$subjectType][$priority] = [];
		}

		$this->votersBySubjectTypeAndPriority[$subjectType][$priority][] = $voter;
		$this->sortedBySubjectType[$subjectType] = false;

		return $this;
	}
}