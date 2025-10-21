<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Enum\ResourceMemberPermissionLevel;
use OCA\OrganizationFolders\Model\Principal;

class BeforeResourceMemberUpdatedEvent extends ResourceMemberEvent {
    public function __construct(
        ResourceMember $member,
        Resource $resource,
        ?string $actorUid = null,
        public readonly ?ResourceMemberPermissionLevel $newPermissionLevel,
        public readonly ?Principal $newPrincipal,
    ) {
        parent::__construct($member, $resource, $actorUid);
    }

    public function getNewPrincipalType(): ?int {
        return $this->newPrincipal?->getType()->value;
    }

    public function getNewPrincipalId(): ?string {
        return $this->newPrincipal?->getId();
    }
}
