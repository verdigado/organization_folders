<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganisationProvider;

abstract class OrganisationProvider {
	protected $id;

	public function getId() {
		return $this->id;
	}

	// TODO: functions to access organisation structure
}