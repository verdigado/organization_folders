<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganizationProvider;

abstract class OrganizationProvider {
	protected $id;

	public function getId() {
		return $this->id;
	}

	// TODO: functions to access organisation structure
}