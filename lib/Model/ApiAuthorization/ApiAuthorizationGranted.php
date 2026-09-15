<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ApiAuthorization;

class ApiAuthorizationGranted extends ApiAuthorizationDecision {
	public function jsonSerialize(): array {
		return [
			"granted" => true,
			"reasons" => $this->getReasons(),
			"reasonsExhaustive" => $this->getReasonsExhaustive(),
		];
	}
}