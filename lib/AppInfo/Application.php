<?php

namespace OCA\OrganizationFolders\AppInfo;

use OCP\AppFramework\App;

class Application extends App {
	public const APP_ID = 'organization_folders';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}
}