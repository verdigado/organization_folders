<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ResourceTemplateProviderNotFound extends NotFoundException {
	public function __construct(public readonly string $id) {
		parent::__construct(
			entity: \OCA\OrganizationFolders\Public\Provider\ResourceTemplate\AbstractResourceTemplateProvider::class,
			criteria: ["id" => $id],
		);
	}
}