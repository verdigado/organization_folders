<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\ResourceMember;

abstract class ResourceMemberEvent extends CancellableEvent {
    public function __construct(
        public readonly ResourceMember $member,
        public readonly Resource       $resource,
        public readonly ?string        $actorUid = null,
    ) {
        parent::__construct();
    }

    public function getPrincipalId(): string {
        return $this->member->getPrincipalId();
    }

    public function getPrincipalType(): int {
        return $this->member->getPrincipalType();
    }

    public function getOrganizationFolderId(): int {
        return $this->resource->getOrganizationFolderId();
    }
}
