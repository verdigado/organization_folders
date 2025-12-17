<?php

namespace OCA\OrganizationFolders\Errors\Api;

use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Db\FolderResource;

class ResourceTemplateNotAvailable extends ApiError {
	public function __construct(
		public readonly OrganizationFolder $organizationFolder,
		public readonly ?FolderResource $parentResource
	) {
		parent::__construct(...$this->t("This resource template is not available for this organization folder or for this parent-resource"));
	}
}
