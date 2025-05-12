<?php

namespace OCA\OrganizationFolders\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {
	public function __construct(
		protected readonly IL10N $l10n,
		protected readonly IURLGenerator $urlGenerator
	) {
	}

	public function getID() {
		return 'organization_folders';
	}

	public function getName(): string {
		return $this->l10n->t('Organization Folders');
	}

	public function getPriority(): int {
		return 90;
	}

	public function getIcon() {
		return $this->urlGenerator->imagePath('groupfolders', 'app-dark.svg');
	}
}
