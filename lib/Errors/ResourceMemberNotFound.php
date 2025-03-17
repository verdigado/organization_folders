<?php

namespace OCA\OrganizationFolders\Errors;

class ResourceMemberNotFound extends NotFoundException {
    public function __construct($criteria) {
		parent::__construct(\OCA\OrganizationFolders\Db\ResourceMember::class, $criteria);
	}
}