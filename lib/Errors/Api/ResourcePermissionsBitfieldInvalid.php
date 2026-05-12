<?php

namespace OCA\OrganizationFolders\Errors\Api;

class ResourcePermissionsBitfieldInvalid extends ApiError {
	public function __construct(public readonly int $bitfield, public readonly int $min, public readonly int $max) {
		parent::__construct(
			...$this->t("Resource permissions bitfield value must be between %d and %d, is %d", [$min, $max, $bitfield]),
		);
	}
}
