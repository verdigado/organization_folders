<?php

namespace OCA\OrganizationFolders\Errors;

class ResourceMemberNotFound extends NotFoundException {
    public function __construct($id) {
		parent::__construct(\OCA\OrganizationFolders\Db\ResourceMember::class, ["id" => $id]);
	}
}