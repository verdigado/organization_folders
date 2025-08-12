<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ResourceMemberNotFound extends NotFoundException {
	public function __construct(array $criteria) {
		parent::__construct(\OCA\OrganizationFolders\Db\ResourceMember::class, $criteria);
	}
}