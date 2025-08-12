<?php

namespace OCA\OrganizationFolders\Errors\Api;

class OrganizationFolderMemberNotFound extends NotFoundException {
	public function __construct($id) {
		parent::__construct(\OCA\OrganizationFolders\Db\OrganizationFolderMember::class, ["id" => $id]);
	}
}