<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Model\OrganizationFolder;

class ResourceTypeNotEnabled extends ApiError {
	public function __construct(public readonly OrganizationFolder $organizationFolder, public readonly string $type) {
		parent::__construct(
			...$this->t("Resource type \"%s\" is not enabled in organization folder \"%s\"", [$type, $organizationFolder->getName()]),
		);
	}
}
