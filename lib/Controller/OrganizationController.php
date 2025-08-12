<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\OrganizationFolders\Security\AuthorizationService;
use OCA\OrganizationFolders\Validation\ValidatorService;
use OCA\OrganizationFolders\OrganizationProvider\OrganizationProviderManager;

class OrganizationController extends BaseController {
	use Errors;

	public function __construct(
		AuthorizationService $authorizationService,
		ValidatorService $validatorService,
		private OrganizationProviderManager $oganizationProviderManager,
	) {
		parent::__construct($authorizationService, $validatorService);
	}

	#[NoAdminRequired]
	public function getOrganizationProviders(): JSONResponse {
		return $this->handleErrors(function () {
            $result = [];

            $organizationProviders = $this->oganizationProviderManager->getOrganizationProviders();

            foreach($organizationProviders as $organizationProvider) {
                $result[] = [
                    "id" => $organizationProvider->getId(),
                    "friendlyName" => $organizationProvider->getFriendlyName(),
                ];
            }

            return $result;
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