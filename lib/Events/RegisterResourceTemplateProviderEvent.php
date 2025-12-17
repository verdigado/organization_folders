<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Events;

use OCP\EventDispatcher\Event;

use OCA\OrganizationFolders\Registry\ResourceTemplateProviderRegistry;
use OCA\OrganizationFolders\Public\Provider\ResourceTemplate\AbstractResourceTemplateProvider;

/**
 * Other apps can use this event to register a ResourceTemplateProvider
 */
class RegisterResourceTemplateProviderEvent extends Event {

	public function __construct(private ResourceTemplateProviderRegistry $resourceTemplateProviderRegistry) {
		parent::__construct();
	}

	public function getRegistry(): ResourceTemplateProviderRegistry {
		return $this->resourceTemplateProviderRegistry;
	}

	public function registerProvider(AbstractResourceTemplateProvider $resourceTemplateProvider): void {
		$this->resourceTemplateProviderRegistry->registerResourceTemplateProvider($resourceTemplateProvider);
	}
}