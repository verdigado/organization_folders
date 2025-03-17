<?php

namespace OCA\OrganizationFolders\Errors;

class ResourceNotFound extends NotFoundException {
	public function __construct($id) {
		parent::__construct(\OCA\OrganizationFolders\Db\Resource::class, ["id" => $id]);
	}
}