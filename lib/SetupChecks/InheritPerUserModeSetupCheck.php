<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\SetupChecks;

use OCP\IAppConfig;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class InheritPerUserModeSetupCheck implements ISetupCheck {
	public function __construct(
		private readonly IL10N $l10n,
		private readonly IAppConfig $config,
	) {}
	
	public function getName(): string {
		return $this->l10n->t('Team folders inherit-per-user mode');
	}

	public function getCategory(): string {
		return 'security';
	}

	public function run(): SetupResult {
		if ($this->config->getValueString('groupfolders', 'acl-inherit-per-user', 'false') === 'true') {
			return SetupResult::warning(
				description: $this->l10n->t('Team folders inherit-per-user mode is enabled on this instance. This is not supported by the OrganizationFolders app and permissions in organization folders will not work as expected!')
			);
		} else {
			return SetupResult::success(
				description: $this->l10n->t('Team folders inherit-per-user mode is disabled. Organization folder permissions will work as expected.')
			);
		}
	}
}