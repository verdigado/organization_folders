<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;

class AclList {
	/** @var array[string]Rule */
	private array $rules = [];

	public function __construct(private int $fileId) {}


	public function getFileId(): int {
		return $this->fileId;
	}

	public function addRule(?IUserMapping $userMapping, int $mask, int $permissions): ?Rule {
		if(!is_null($userMapping)) {
			$key = $userMapping->getKey();

			$existingRule = $this->rules[$key] ?? null;

			$newRule = new Rule(
				userMapping: $userMapping,
				fileId: $this->fileId,
				mask: ($existingRule?->getMask() ?? 0) | $mask,
				permissions: ($existingRule?->getPermissions() ?? 0) | $permissions,
			);

			$this->rules[$key] = $newRule;

			return $newRule;
		}

		return null;
	}

	/**
	 * @psalm-return Rule[]
	 */
	public function getRules(): array {
		return array_values($this->rules);
	}
}