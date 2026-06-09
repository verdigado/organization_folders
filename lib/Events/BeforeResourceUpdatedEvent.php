<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCA\OrganizationFolders\Db\Resource;


class BeforeResourceUpdatedEvent extends ResourceEvent {
    public function __construct(
        Resource $resource,

        public readonly ?bool $newActive,
		public readonly ?bool $newInheritManagers,
		public readonly ?array $newMemberPermissions,
		public readonly ?array $newManagerPermissions,
		public readonly ?array $newInheritedMemberPermissions,

        ?string $actorUid = null,
    ) {
        parent::__construct($resource, $actorUid);
    }
}
