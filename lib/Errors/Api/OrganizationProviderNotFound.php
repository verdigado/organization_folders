<?php

namespace OCA\OrganizationFolders\Errors\Api;

class OrganizationProviderNotFound extends NotFoundException {
	public function __construct(public readonly string $id) {
		parent::__construct(
			entity: \OCA\OrganizationFolders\OrganizationProvider\OrganizationProvider::class,
			criteria: ["id" => $id],
		);
	}
}