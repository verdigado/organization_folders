<?php

namespace OCA\OrganizationFolders\Errors;

class ResourceSnapshotDiffTaskNotFound extends NotFoundException {
	public function __construct(array $criteria) {
		parent::__construct(\OCA\GroupfolderFilesystemSnapshots\Db\DiffTask::class, $criteria);
	}
}