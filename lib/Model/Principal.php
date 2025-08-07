<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IUser;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;

use OCA\OrganizationFolders\Enum\PrincipalType;

abstract class Principal implements \JsonSerializable {
	protected bool $valid;

	abstract public function getType(): PrincipalType;

	abstract public function getId(): string;

	abstract public function getFriendlyName(): string;

	public function isValid(): bool {
		return $this->valid;
	}

	/**
	 * @return array
	 * @psalm-return string[]
	 */
	public function getFullHierarchyNames(): array {
		return [$this->getFriendlyName()];
	}

	abstract public function getNumberOfUsersContained(): int;

	/**
	 * @return IUser[]
	 */
	abstract public function getUsersContained(): array;

	abstract public function toGroupfolderAclMapping(): ?IUserMapping;

	/**
	 * Return if the given principal references the same permissions subject.
	 * Unlike checking principal equality through comparing type+id this
	 * returns true for example if both principals are backed by the same group.
	*/
	abstract public function isEquivalent(Principal $principal): bool;

	/**
	 * Returns true if all users contained in the given principal are contained in this principal
	 * 
	 * @param Principal $principal
	 * @param bool $skipExpensiveOperations Allow false-negatives (no false-positives) to avoid expensive operations
	 */
	abstract public function containsPrincipal(Principal $principal, bool $skipExpensiveOperations = false): bool;

	public function jsonSerialize(): array {
		return [
			'type' => $this->getType(),
			'id' => $this->getId(),
			'valid' => $this->valid,
			'friendlyName' => $this->getFriendlyName(),
			'fullHierarchyNames' => $this->getFullHierarchyNames(),
		];
	}

	public function getKey(): string {
		return $this->getType()->name . ":" . $this->getId();
	}

	public function __toString(): string {
		return $this->getKey();
	}
}