<?php

namespace OCA\OrganizationFolders\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

abstract class Resource extends Entity implements JsonSerializable {
	protected $groupFolderId;
	protected $parentResource;
	protected $name;
    protected $active;
	protected $lastUpdatedTimestamp;
	
	public function __construct() {
		$this->addType('groupFolderId','integer');
		$this->addType('parentResource','integer');
        $this->addType('active','bool');
		$this->addType('lastUpdatedTimestamp','integer');
	}
}
