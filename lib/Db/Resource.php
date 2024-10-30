<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

abstract class Resource extends Entity implements JsonSerializable {
	protected $organizationFolderId;
	protected $parentResource;
	protected $name;
    protected $active;
	protected $lastUpdatedTimestamp;
	
	public function __construct() {
		$this->addType('organizationFolderId','integer');
		$this->addType('parentResource','integer');
        $this->addType('active','bool');
		$this->addType('lastUpdatedTimestamp','integer');
	}
}
