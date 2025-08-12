<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ResourceSnapshotDiffTaskResultNotFound extends NotFoundException {
	public function __construct(array $criteria) {
		parent::__construct(\OCA\GroupfolderFilesystemSnapshots\Db\DiffTaskResult::class, $criteria);
	}
}