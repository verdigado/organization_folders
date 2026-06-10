<?php

namespace OCA\OrganizationFolders\Errors\Api;

class UserNotFound extends ApiError {
	public function __construct(
        public readonly string $uid,
    ) {
		parent::__construct(
			...$this->t("A user with ID %s does not exist", [$uid])
		);
	}
}
