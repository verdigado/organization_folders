<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ResourceSnapshotNotFound extends NotFoundException {
	public function __construct(array $criteria) {
		parent::__construct(\OCA\GroupfolderFilesystemSnapshots\Entity\Snapshot::class, $criteria);
	}
}