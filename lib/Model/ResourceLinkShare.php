<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

abstract class ResourceLinkShare implements \JsonSerializable {

	abstract public function getResourceId(): int;

	abstract public function getId(): int;

	abstract public function getName(): string;
	
	abstract public function getLinkURL(): string;
}