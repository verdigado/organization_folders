<?php

namespace OCA\OrganizationFolders\Errors\Api;

class SnapshotIntegrationNotActive extends ApiError {
	public function __construct() {
		parent::__construct(...$this->t("The snapshot integration is not active"));
	}
}
