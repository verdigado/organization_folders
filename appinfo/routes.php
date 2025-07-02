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
		['name' => 'resource#destroy', 'url' => '/resources/{resourceId}', 'verb' => 'DELETE'],
		['name' => 'resource#subResources', 'url' => '/resources/{resourceId}/subResources', 'verb' => 'GET'],
		['name' => 'resource#findGroupMemberOptions', 'url' => '/resources/{resourceId}/groupMemberOptions', 'verb' => 'GET'],
		['name' => 'resource#findUserMemberOptions', 'url' => '/resources/{resourceId}/userMemberOptions', 'verb' => 'GET'],

		/* Resource Members */
		['name' => 'resource_member#index', 'url' => '/resources/{resourceId}/members', 'verb' => 'GET'],
		['name' => 'resource_member#create', 'url' => '/resources/{resourceId}/members', 'verb' => 'POST'],
		['name' => 'resource_member#update', 'url' => '/resources/members/{id}', 'verb' => 'PUT'],
		['name' => 'resource_member#destroy', 'url' => '/resources/members/{id}', 'verb' => 'DELETE'],

		/* Resource Snapshots */
		['name' => 'resource_snapshot#index', 'url' => '/resources/{resourceId}/snapshots', 'verb' => 'GET'],
		['name' => 'resource_snapshot#show', 'url' => '/resources/{resourceId}/snapshots/{snapshotId}', 'verb' => 'GET'],
		['name' => 'resource_snapshot_diff#create', 'url' => '/resources/{resourceId}/snapshots/{snapshotId}/diff', 'verb' => 'POST'],
		['name' => 'resource_snapshot_diff#show', 'url' => '/resources/{resourceId}/snapshots/{snapshotId}/diff/{diffTaskId}', 'verb' => 'GET'],
		['name' => 'resource_snapshot_diff_result#show','url' => '/resources/{resourceId}/snapshots/{snapshotId}/diff/{diffTaskId}/{diffTaskResultId}', 'verb' => 'GET'],
		['name' => 'resource_snapshot_diff_result#revert', 'url' => '/resources/{resourceId}/snapshots/{snapshotId}/diff/{diffTaskId}/{diffTaskResultId}/revert', 'verb' => 'POST'],

		/* Organizations */
		['name' => 'organization#getOrganizationProviders', 'url' => '/organizationProviders', 'verb' => 'GET'],
		['name' => 'organization#getOrganization', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{organizationId}', 'verb' => 'GET'],
		['name' => 'organization#getSubOrganizations', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{parentOrganizationId}/subOrganizations', 'verb' => 'GET'],
		['name' => 'organization#getTopLevelOrganizations', 'url' => '/organizationProviders/{organizationProviderId}/subOrganizations', 'verb' => 'GET'],
		['name' => 'organization#getRoles', 'url' => '/organizationProviders/{organizationProviderId}/organizations/{organizationId}/roles/', 'verb' => 'GET'],

		/* Admin Settings */
		['name' => 'AdminSettings#index', 'url' => '/adminSettings', 'verb' => 'GET'],
		['name' => 'AdminSettings#show', 'url' => '/adminSettings/{key}', 'verb' => 'GET'],
		['name' => 'AdminSettings#update', 'url' => '/adminSettings/{key}', 'verb' => 'PATCH'],

	],
];