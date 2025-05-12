<?php

namespace OCA\OrganizationFolders\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

use OCA\OrganizationFolders\AppInfo\Application;

class Admin implements ISettings {
	public function __construct() {
	}

	public function getForm() {
		return new TemplateResponse(Application::APP_ID, 'settings/admin');
	}

	public function getSection() {
		return 'organization_folders';
	}

	public function getPriority() {
		return 50;
	}
}
