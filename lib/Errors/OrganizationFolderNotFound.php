<?php

namespace OCA\OrganizationFolders\Errors;

class OrganizationFolderNotFound extends NotFoundException {
	public function __construct(array $criteria) {
		parent::__construct(\OCA\OrganizationFolders\Model\OrganizationFolder::class, $criteria);
	}
}