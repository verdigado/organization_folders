<?php

return [
	'resources' => [
	],
	'routes' => [
		/* Resources */
		['name' => 'resource#show', 'url' => '/resources/{resourceId}', 'verb' => 'GET'],
		['name' => 'resource#create', 'url' => '/resources/{resourceId}', 'verb' => 'POST'],
		['name' => 'resource#update', 'url' => '/resources/{resourceId}', 'verb' => 'PUT'],
		['name' => 'resource#subResources', 'url' => '/resources/{resourceId}/subResources', 'verb' => 'GET'],
		['name' => 'resource_member#index', 'url' => '/resources/{resourceId}/members', 'verb' => 'GET'],
		['name' => 'resource_member#create', 'url' => '/resources/{resourceId}/members', 'verb' => 'POST'],
		['name' => 'resource_member#update', 'url' => '/resources/members/{id}', 'verb' => 'PUT'],
		['name' => 'resource_member#destroy', 'url' => '/resources/members/{id}', 'verb' => 'DELETE'],
		['name' => 'organization#getOrganizationProviders', 'url' => '/organizationProviders', 'verb' => 'GET'],
		['name' => 'organization#getOrganization', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{organizationId}', 'verb' => 'GET'],
		['name' => 'organization#getSubOrganizations', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{parentOrganizationId}/subOrganizations', 'verb' => 'GET'],
		['name' => 'organization#getTopLevelOrganizations', 'url' => '/organizationProviders/{organizationProviderId}/subOrganizations', 'verb' => 'GET'],
		['name' => 'organization#getRoles', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{organizationId}/roles/', 'verb' => 'GET'],
	],
];