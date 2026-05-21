<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ResourceLinkShareNotFound extends NotFoundException {
	public function __construct(public readonly int $resourceId, public readonly int $id) {
		parent::__construct(
			entity: \OCA\OrganizationFolders\Model\ResourceLinkShare::class,
			criteria: ["resourceId" => $resourceId, "id" => $id],
		);
	}
}