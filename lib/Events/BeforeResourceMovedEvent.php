<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCA\OrganizationFolders\Db\Resource;


class BeforeResourceMovedEvent extends ResourceEvent {
    public function __construct(
        Resource $resource,

		public readonly string $newName,
		public readonly ?int $newParentResourceId,

		?string $actorUid = null,
    ) {
        parent::__construct($resource, $actorUid);
    }
}
