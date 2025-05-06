<?php

namespace OCA\OrganizationFolders\Errors;

use OCA\DAV\Connector\Sabre\Exception\Forbidden;

class CannotCreateFileInRootOfOrganizationFolder extends Forbidden {
	public function __construct() {
		parent::__construct(
			message: "You cannot create a file or folder in the root directory of an organization folder."
			. " Create a folder resource to put files into in the organization folder user interface."
		);
	}
}
