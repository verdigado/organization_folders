<?php

namespace OCA\OrganizationFolders\Errors;


class SnapshotIntegrationNotActive extends \RuntimeException {
	public function __construct() {
		parent::__construct(
            message: "The snapshot integration is not active",
        );
	}
}
