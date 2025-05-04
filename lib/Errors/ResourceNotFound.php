<?php

namespace OCA\OrganizationFolders\Errors;

class ResourceNotFound extends NotFoundException {
	public function __construct(array $criteria) {
		parent::__construct(\OCA\OrganizationFolders\Db\Resource::class, $criteria);
	}
}