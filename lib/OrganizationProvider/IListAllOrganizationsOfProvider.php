<?php

declare(strict_types=1);

namespace OCA\OrganizationFolders\OrganizationProvider;

use OCA\OrganizationFolders\Model\Organization;

/**
 * For OrganizationProviders, that can not only fetch direct sub-organizations of an organization, but can fetch the entire tree (as a flat list) at once.
 */
interface IListAllOrganizationsOfProvider {
	/**
	 * Get the entire organization tree as a flat list
	 * 
	 * @return Organization[]
	 */
	public function getAllOrganizations(): array;
}