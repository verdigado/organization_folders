<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCA\OrganizationFolders\Db\Resource;

abstract class ResourceEvent extends CancellableEvent {
    public function __construct(
        public readonly Resource       $resource,
        public readonly ?string        $actorUid = null,
    ) {
        parent::__construct();
    }
}
