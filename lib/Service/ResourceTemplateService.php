<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\Service;

use OCA\OrganizationFolders\Db\Resource;
use OCA\OrganizationFolders\Db\ResourceMember;
use OCA\OrganizationFolders\Errors\Api\ResourceTemplateNotAvailable;
use OCA\OrganizationFolders\Registry\ResourceTemplateProviderRegistry;
use OCA\OrganizationFolders\Errors\Api\ResourceDoesNotSupportSubresources;
use OCA\OrganizationFolders\Errors\Api\ResourceTemplateProviderNotFound;
use OCA\OrganizationFolders\Public\Errors\ResourceTemplateNotFound;
use OCP\AppFramework\Db\TTransactional;
use OCP\IDBConnection;

class ResourceTemplateService {
	use TTransactional;

	public function __construct(
		protected readonly ResourceTemplateProviderRegistry $resourceTemplateProviderRegistry,
		protected readonly OrganizationFolderService $organizationFolderService,
		protected readonly ResourceService $resourceService,
		protected readonly ResourceMemberService $resourceMemberService,
		protected readonly IDBConnection $db,
	) {}
	
	/**
	 * @param string $providerId
	 * @param string $templateId
	 * @param int $organizationFolderId
	 * @param ?int $parentResourceId
	 * 
	 * @throws ResourceTemplateProviderNotFound
	 * @throws ResourceTemplateNotFound
	 * @throws ResourceDoesNotSupportSubresources
	 * @throws ResourceTemplateNotAvailable
	 * 
	 * @psalm-return array{resource: Resource, members: ResourceMember[]}
	 */
	public function createFromResourceTemplate(
		string $providerId,
		string $templateId,
		int $organizationFolderId,
		?int $parentResourceId,
	): array {
		$provider = $this->resourceTemplateProviderRegistry->getResourceTemplateProvider($providerId);
		$template = $provider->getTemplateById($templateId);
		$organizationFolder = $this->organizationFolderService->find($organizationFolderId);

		if(isset($parentResourceId)) {
			$parentResource = $this->resourceService->find($parentResourceId);

			if($parentResource->getOrganizationFolderId() !== $organizationFolder->getId()) {
				throw new \Exception("Cannot create child-resource of parent in different organization folder");
			}

			if($parentResource->getType() !== "folder") {
				throw new ResourceDoesNotSupportSubresources($parentResource);
			}
		} else {
			$parentResource = null;
		}

		if(!$template->isAvailable($organizationFolder, $parentResource)) {
			throw new ResourceTemplateNotAvailable($organizationFolder, $parentResource);
		}

		$createDTO = $template->apply($organizationFolder, $parentResource);

		return $this->atomic(function () use ($providerId, $createDTO, $template): array {
			$resource = $this->resourceService->createFromDTO($createDTO, $providerId . ":" . $template->getId());

			$memberDTOs = $createDTO->splitOutCreateMemberDTOs($resource->getId());

			$members = [];

			foreach($memberDTOs as $memberDTO) {
				$members[] = $this->resourceMemberService->createFromDTO($memberDTO);
			}

			return [
				"resource" => $resource,
				"members" => $members,
			];
		}, $this->db);
	}
}