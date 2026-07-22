<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Enum\PrincipalType;

class PrincipalFilter {
	public function __construct(
		public readonly PrincipalType $type,
		public readonly ?string $id = null,
	) {}

	public static function fromPrincipal(Principal $principal): static {
		return new static($principal->getType(), $principal->getId());
	}

	/**
	 * @param Principal[] $principals
	 * @return list<PrincipalFilter>
	 */
	public static function fromPrincipals(array $principals): array {
		$result = [];

		foreach($principals as $principal) {
			$result[] = new static($principal->getType(), $principal->getId());
		}

		return $result;
	}
}