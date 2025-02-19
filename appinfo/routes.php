<?php

return [
	'resources' => [
	],
	'routes' => [
		/* Organization Folders */
		['name' => 'organization_folder#index', 'url' => '/organizationFolders', 'verb' => 'GET'], // ADMIN ONLY
		['name' => 'organization_folder#show', 'url' => '/organizationFolders/{organizationFolderId}', 'verb' => 'GET'],
		['name' => 'organization_folder#create', 'url' => '/organizationFolders', 'verb' => 'POST'], // ADMIN ONLY
		['name' => 'organization_folder#update', 'url' => '/organizationFolders/{organizationFolderId}', 'verb' => 'PUT'],
		['name' => 'organization_folder#resources', 'url' => '/organizationFolders/{organizationFolderId}/resources', 'verb' => 'GET'],

		/* Organization Folder Members */
		['name' => 'organization_folder_member#index', 'url' => '/organizationFolders/{organizationFolderId}/members', 'verb' => 'GET'],
		['name' => 'organization_folder_member#create', 'url' => '/organizationFolders/{organizationFolderId}/members', 'verb' => 'POST'],
		['name' => 'organization_folder_member#update', 'url' => '/organizationFolders/members/{id}', 'verb' => 'PUT'],
		['name' => 'organization_folder_member#destroy', 'url' => '/organizationFolders/members/{id}', 'verb' => 'DELETE'],

		/* Resources */
		['name' => 'resource#show', 'url' => '/resources/{resourceId}', 'verb' => 'GET'],
		['name' => 'resource#create', 'url' => '/resources', 'verb' => 'POST'],
		['name' => 'resource#update', 'url' => '/resources/{resourceId}', 'verb' => 'PUT'],
		['name' => 'resource#subResources', 'url' => '/resources/{resourceId}/subResources', 'verb' => 'GET'],

		/* Resource Members */
		['name' => 'resource_member#index', 'url' => '/resources/{resourceId}/members', 'verb' => 'GET'],
		['name' => 'resource_member#create', 'url' => '/resources/{resourceId}/members', 'verb' => 'POST'],
		['name' => 'resource_member#update', 'url' => '/resources/members/{id}', 'verb' => 'PUT'],
		['name' => 'resource_member#destroy', 'url' => '/resources/members/{id}', 'verb' => 'DELETE'],

		/* Organizations */
		['name' => 'organization#getOrganizationProviders', 'url' => '/organizationProviders', 'verb' => 'GET'],
		['name' => 'organization#getOrganization', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{organizationId}', 'verb' => 'GET'],
		['name' => 'organization#getSubOrganizations', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{parentOrganizationId}/subOrganizations', 'verb' => 'GET'],
		['name' => 'organization#getTopLevelOrganizations', 'url' => '/organizationProviders/{organizationProviderId}/subOrganizations', 'verb' => 'GET'],
		['name' => 'organization#getRoles', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{organizationId}/roles/', 'verb' => 'GET'],
	],
];