<?php

namespace OCA\OrganizationFolders\Errors;

class OrganizationFolderNotFound extends NotFoundException {
	public function __construct($id) {
		parent::__construct(\OCA\OrganizationFolders\Model\OrganizationFolder::class, ["id" => $id]);
	}
}