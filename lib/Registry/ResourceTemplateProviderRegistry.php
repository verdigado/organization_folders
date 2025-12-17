<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Registry;

use OCP\EventDispatcher\IEventDispatcher;

use OCA\OrganizationFolders\Errors\Api\ResourceTemplateProviderNotFound;
use OCA\OrganizationFolders\Events\RegisterResourceTemplateProviderEvent;
use OCA\OrganizationFolders\Public\Provider\AbstractResourceTemplateProvider;

class ResourceTemplateProviderRegistry {
	private array $resourceTemplateProviders = [];

	public function __construct(
		IEventDispatcher $dispatcher,
	) {
		$event = new RegisterResourceTemplateProviderEvent($this);
		$dispatcher->dispatchTyped($event);
	}

	/**
	 * @return AbstractResourceTemplateProvider[]
	 */
	public function getResourceTemplateProviders(): array {
		return $this->resourceTemplateProviders;
	}

	/**
	 * @return bool
	 */
	public function hasResourceTemplateProvider($id): bool {
		return array_key_exists($id, $this->resourceTemplateProviders);
	}

	/**
	 * @return AbstractResourceTemplateProvider
	 * @throws ResourceTemplateProviderNotFound
	 */
	public function getResourceTemplateProvider(string $id): AbstractResourceTemplateProvider {
		$resourceTemplateProvider = $this->resourceTemplateProviders[$id];
		
		if(isset($resourceTemplateProvider)) {
			return $resourceTemplateProvider;
		} else {
			throw new ResourceTemplateProviderNotFound($id);
		}
	}

	public function registerResourceTemplateProvider(AbstractResourceTemplateProvider $resourceTemplateProvider): self {
		$this->resourceTemplateProviders[$resourceTemplateProvider->getId()] = $resourceTemplateProvider;
		return $this;
	}
}