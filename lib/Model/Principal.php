<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCP\IUser;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;

use OCA\OrganizationFolders\Enum\PrincipalType;

abstract class Principal implements \JsonSerializable {

	public function __construct(
		protected readonly PrincipalFactory $factory,
	) {}

	abstract public function getType(): PrincipalType;

	abstract public function getId(): string;

	abstract public function isValid(): bool;

	abstract public function getFriendlyName(): string;

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

	abstract public function toDavPrincipalURI(): string;

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
	 */
	abstract public function containsPrincipal(Principal $principal): bool;

	/**
	 * Get all principals this principal is contained in (e.g. which would return true if called ->containsPrincipal($this) on)
	 * @return list<Principal>
	 */
	abstract public function getPrincipalsIsContainedIn(): array;

	public function jsonSerialize(): array {
		return [
			'type' => $this->getType(),
			'id' => $this->getId(),
			'valid' => $this->isValid(),
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