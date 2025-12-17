<?php

namespace OCA\OrganizationFolders\Public\Provider\ResourceTemplate;

use OCA\OrganizationFolders\Model\OrganizationFolder;
use OCA\OrganizationFolders\Db\FolderResource;
use OCA\OrganizationFolders\DTO\CreateResourceWithMembersDto;

abstract class AbstractResourceTemplate {
	public function __construct(protected string $id) {}

	public function getId(): string {
		return $this->id;
	}

	public function getFriendlyName(): string {
		return $this->getId();
	}

	public function getDescription(): string {
		return "";
	}

	/**
	 * Return whether the template is available to be created within $organizationFolder as child of $parentResource.
	 * $parentResource is null when querying availability for top-level.
	 * 
	 * @param OrganizationFolder $organizationFolder
	 * @param ?FolderResource $parentResource
	 * @return bool
	 */
	abstract function isAvailable(OrganizationFolder $organizationFolder, ?FolderResource $parentResource): bool;

	/**
	 * Return DTO to create from template within $organizationFolder as child of $parentResource.
	 * 
	 * @param OrganizationFolder $organizationFolder
	 * @param ?FolderResource $parentResource
	 * @return CreateResourceWithMembersDto
	 */

	abstract function apply(OrganizationFolder $organizationFolder, ?FolderResource $parentResource): CreateResourceWithMembersDto;
}