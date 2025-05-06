<?php

namespace OCA\OrganizationFolders\Errors;

use OCA\DAV\Connector\Sabre\Exception\Forbidden;

class CannotMoveFileOutOfOrganizationFolder extends Forbidden {
	public function __construct() {
		parent::__construct(
			message: "You cannot move files or folders out of this organization folder."
		);
	}
}
