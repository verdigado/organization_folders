<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model\ApiAuthorization;

class ApiAuthorizationDenied extends ApiAuthorizationDecision {
	public function jsonSerialize(): array {
		return [
			"granted" => false,
			"reasons" => $this->getReasons(),
			"reasonsExhaustive" => $this->getReasonsExhaustive(),
		];
	}
}