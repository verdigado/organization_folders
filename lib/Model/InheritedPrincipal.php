<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Model;

use OCA\OrganizationFolders\Db\Resource;

/**
 * Temporary datastructure used while generating a ResourceRermissionsList, to keep track of the origin of the inheritance of a principal
 */
class InheritedPrincipal {
	public function __construct(
		private readonly Principal $principal,
		private readonly OrganizationFolder|Resource $origin,
	){}

	public function getPrincipal(): Principal {
		return $this->principal;
	}

	public function getOrigin(): OrganizationFolder|Resource {
		return $this->origin;
	}
}