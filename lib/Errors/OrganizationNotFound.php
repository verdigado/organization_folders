<?php

namespace OCA\OrganizationFolders\Errors;

class OrganizationNotFound extends NotFoundException {
	public function __construct(string $provider, int $id) {
		parent::__construct(\OCA\OrganizationFolders\Model\Organization::class, ["provider" => $provider, "id" => $id]);
	}
}