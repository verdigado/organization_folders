<?php

namespace OCA\OrganizationFolders\Errors;

use OCA\DAV\Connector\Sabre\Exception\Forbidden;

class CannotDeleteResourceInFilesystem extends Forbidden {
	public function __construct(public string $path) {
		parent::__construct(
			message: "You cannot delete the folder \"" . $path . "\" as it is an organization folder resource."
			. "If you want to delete the resource, you must delete it in the organization folder user interface."
		);
	}
}
