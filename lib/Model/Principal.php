<?php

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Enum\PrincipalType;

abstract class Principal implements \JsonSerializable {
	protected bool $valid;

    abstract public function getType(): PrincipalType;

    abstract public function getId(): string;

	abstract public function getFriendlyName(): string;

	/**
	 * @return array
	 * @psalm-return string[]
	 */
	public function getFullHierarchyNames(): array {
		return [$this->getFriendlyName()];
	}

	public function jsonSerialize(): array {
		return [
			'type' => $this->getType(),
			'id' => $this->getId(),
			'valid' => $this->valid,
			'friendlyName' => $this->getFriendlyName(),
			'fullHierarchyNames' => $this->getFullHierarchyNames(),
		];
	}
}