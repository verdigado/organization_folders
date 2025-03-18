<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class OrganizationController extends BaseController {
	use Errors;

	public function __construct(
		private OrganizationProviderManager $oganizationProviderManager,
	) {
		parent::__construct();
	}

	#[NoAdminRequired]
	public function getOrganizationProviders(): JSONResponse {
		return $this->handleErrors(function () {
			return array_keys($this->oganizationProviderManager->getOrganizationProviders());
		});
	}

	#[NoAdminRequired]
	public function getOrganization(string $organizationProviderId, int $organizationId): JSONResponse {
		return $this->handleErrors(function () use ($organizationProviderId, $organizationId) {
			$organizationProvider = $this->oganizationProviderManager->getOrganizationProvider($organizationProviderId);

			return $organizationProvider->getOrganization($organizationId);
		});
	}

	#[NoAdminRequired]
	public function getTopLevelOrganizations(string $organizationProviderId): JSONResponse {
		return $this->handleErrors(function () use ($organizationProviderId) {
			$organizationProvider = $this->oganizationProviderManager->getOrganizationProvider($organizationProviderId);

			return $organizationProvider->getSubOrganizations();
		});
	}

	#[NoAdminRequired]
	public function getSubOrganizations(string $organizationProviderId, int $parentOrganizationId): JSONResponse {
		return $this->handleErrors(function () use ($organizationProviderId, $parentOrganizationId) {
			$organizationProvider = $this->oganizationProviderManager->getOrganizationProvider($organizationProviderId);

			return $organizationProvider->getSubOrganizations($parentOrganizationId);
		});
	}

	#[NoAdminRequired]
	public function getRoles(string $organizationProviderId, int $organizationId): JSONResponse {
		return $this->handleErrors(function () use ($organizationProviderId, $organizationId) {
			$organizationProvider = $this->oganizationProviderManager->getOrganizationProvider($organizationProviderId);

			return $organizationProvider->getRolesOfOrganization($organizationId);
		});
	}
}