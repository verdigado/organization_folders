<?php

namespace OCA\OrganizationFolders\ApiPermissionsVoter;

interface VoterSubject {
	public const VOTER_SUBJECT_TYPE = "";

	public function getVoterSubjectKey(): string;

}