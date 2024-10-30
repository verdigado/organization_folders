<?php

namespace OCA\OrganizationFolders\Errors;

class OrganizationRoleNotFound extends NotFoundException {
    public function __construct($provider, $id) {
		parent::__construct(\OCA\OrganizationFolders\Model\OrganizationRole::class, ["provider" => $provider, "id" => $id]);
	}
}