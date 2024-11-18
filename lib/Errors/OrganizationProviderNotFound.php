<?php

namespace OCA\OrganizationFolders\Errors;

class OrganizationProviderNotFound extends NotFoundException {
    public function __construct(string $id) {
		parent::__construct(\OCA\OrganizationFolders\OrganizationProvider\OrganizationProvider::class, ["id" => $id]);
	}
}