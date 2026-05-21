import axios from "@nextcloud/axios";
import { getRequestToken } from '@nextcloud/auth';
import { generateUrl } from "@nextcloud/router";
import { showError } from "@nextcloud/dialogs";
import { translate as t } from "@nextcloud/l10n";

/**
 * @typedef {number} PrincipalType
 **/

/**
 * @enum {PrincipalType}
 */
var PrincipalTypes = {
	USER: 1,
	GROUP: 2,
	ORGANIZATION_MEMBER: 3,
	ORGANIZATION_ROLE: 4,
}

/**
 * @typedef {number} ResourceType
 **/

/**
 * @enum {ResourceType}
 */
var ResourceTypes = {
	FOLDER: "folder",
	CALENDAR: "calendar",
}

/**
 * @typedef {number} OrganizationFolderMemberPermissionLevel
 **/

/**
 * @enum {OrganizationFolderMemberPermissionLevel}
 */
var OrganizationFolderMemberPermissionLevels = {
	MEMBER: 1,
	MANAGER: 2,
	ADMIN: 3,
}

/**
 * @typedef {number} ResourceMemberPermissionLevel
 **/

/**
 * @enum {ResourceMemberPermissionLevel}
 */
var ResourceMemberPermissionLevels = {
	MEMBER: 1,
	MANAGER: 2,
}

/**
 * @typedef {number} PermissionOriginType
 **/

/**
 * @enum {PermissionOriginType}
 */
var PermissionOriginTypes = {
	MEMBER: 1,
	MANAGER: 2,
	INHERITED_MEMBER: 3,
	INHERITED_MANAGER: 4,
}

var RessourcePermissionKeysByType = {
	folder: ["READ", "UPDATE", "CREATE", "DELETE", "SHARE"],
	calendar: ["READ", "UPDATE"],
}

var RessourcePermissionKeyLabelsByType = {
	folder: [
		// TRANSLATORS Read permission checkbox title
		t("organization_folders", "Read"),
		// TRANSLATORS Write permission checkbox title
		t("organization_folders", "Write"),
		// TRANSLATORS Create permission checkbox title
		t("organization_folders", "Create"),
		// TRANSLATORS Delete permission checkbox title
		t("organization_folders", "Delete"),
		// TRANSLATORS Share permission checkbox title
		t("organization_folders", "Share"),
	],
	calendar: [
		// TRANSLATORS Read permission checkbox title
		t("organization_folders", "Read"),
		// TRANSLATORS Write permission checkbox title
		t("organization_folders", "Write"),
	],
}

var SubresourceSupportByType = {
	folder: true,
}

var LinkShareSupportByType = {
	calendar: true,
}

var SnapshotSupportByType = {
	folder: true,
}

var ResourceDefaultPermissionsByType = {
	folder: {
		memberPermissions: {
			READ: true,
		},
		managerPermissions: {
			READ: true,
			UPDATE: true,
			CREATE: true,
			DELETE: true,
			SHARE: false,
		},
		inheritedMemberPermissions: {},
	},
	calendar: {
		memberPermissions: {
			READ: true,
		},
		managerPermissions: {
			READ: true,
			UPDATE: true,
		},
		inheritedMemberPermissions: {},
	},
}

/**
 * @typedef {{
 * id: string
 * friendlyName: string
 * }} OrganizationProvider
 *
 * @typedef {{
 * id: number
 * name: string
 * quota: number
 * organizationProviderId: string|undefined
 * organizationId: number
 * serviceAccountUid: string
 * enabledResourceTypes: string[]
 * members: Array<OrganizationFolderMember>|undefined
 * resources: Array<Resource>|undefined
 * }} OrganizationFolder
 *
 * @typedef {{
 * id: number
 * organizationFolderId: number
 * permissionLevel: OrganizationFolderMemberPermissionLevel
 * principal: Principal,
 * createdTimestamp: number,
 * lastUpdatedTimestamp: number,
 * }} OrganizationFolderMember
 * 
 * @typedef {{
 * READ: bool,
 * UPDATE: bool,
 * CREATE: bool,
 * DELETE: bool,
 * SHARE: bool,
 * }} FolderRessourcePermissions
 * 
 * @typedef {{
 * READ: bool,
 * UPDATE: bool,
 * }} CalendarRessourcePermissions
 *
 * @typedef {{
 * id: number
 * type: ResourceType
 * organizationFolderId: number
 * name: string
 * parentResourceId: number|null
 * active: bool
 * inheritManagers: bool
 * createdTimestamp: number
 * lastUpdatedTimestamp: number
 * memberPermissions: FolderRessourcePermissions
 * managerPermissions: FolderRessourcePermissions
 * inheritedMemberPermissions: FolderRessourcePermissions
 * members: Array<ResourceMember>|undefined
 * subResources: Array<Resource>|undefined
 * }} FolderResource
 * 
 * @typedef {{
 * id: number
 * type: ResourceType
 * organizationFolderId: number
 * name: string
 * parentResourceId: number|null
 * active: bool
 * inheritManagers: bool
 * createdTimestamp: number
 * lastUpdatedTimestamp: number
 * memberPermissions: CalendarRessourcePermissions
 * managerPermissions: CalendarRessourcePermissions
 * inheritedMemberPermissions: CalendarRessourcePermissions
 * members: Array<ResourceMember>|undefined
 * linkShares: array
 * }} CalendarResource
 *
 * @typedef {(FolderResource)} Resource
 * 
 * @typedef {{
 * id: number
 * name: string
 * createdTimestamp: number
 * }} ResourceSnapshot
 *
 * @typedef {{
 * type: PrincipalType,
 * id: string,
 * friendlyName: string,
 * fullHierarchyNames: string[],
 * }} Principal
 *
 * @typedef {{
 * id: number,
 * resourceId: number,
 * permissionLevel: ResourceMemberPermissionLevel,
 * principal: Principal,
 * createdTimestamp: number,
 * lastUpdatedTimestamp: number,
 * }} ResourceMember
 *
 * @typedef {{
 * id: number,
 * friendlyName: string,
 * membersGroup: string,
 * }} Organization
 * 
 * @typedef {{
 * id: number,
 * resourceId: number,
 * name: string,
 * linkUrl: string,
 * }} ResourceLinkShare
 *
 */

const baseURL = generateUrl("/apps/organization_folders");
axios.defaults.baseURL = baseURL;

const httpErrorCodesNotGloballyHandled = [412];

axios.interceptors.response.use(r => r, function (error) {
	if(!httpErrorCodesNotGloballyHandled.includes(error.response?.status)) {
		if(error.response?.data?.l10nMessage) {
			showError(error.response?.data?.l10nMessage);
		} else {
			showError(t("organization_folders", "An unknown error occurred."));
		}
	}
	
	return Promise.reject(error);
});

export default {
	PrincipalTypes,
	OrganizationFolderMemberPermissionLevels,
	ResourceMemberPermissionLevels,
	ResourceTypes,
	PermissionOriginTypes,
	RessourcePermissionKeysByType,
	RessourcePermissionKeyLabelsByType,
	SubresourceSupportByType,
	LinkShareSupportByType,
	SnapshotSupportByType,
	ResourceDefaultPermissionsByType,

	/* Organization Folders */

	/**
	 * ADMIN ONLY
	 *
	 * @return {Promise<Array<OrganizationFolder>>}
	 */
	getOrganizationFolders() {
		return axios.get(`/organizationFolders`, { params: { } }).then((res) => res.data);
	},

	/**
	 *
	 * @param {number|string} id Organization folder id
	 * @param {string} include
	 * @return {Promise<OrganizationFolder>}
	 */
	getOrganizationFolder(organizationFolderId, include = "model") {
		return axios.get(`/organizationFolders/${organizationFolderId}`, { params: { include } }).then((res) => res.data);
	},

	/**
	 *
	 * @param {number|string} id Organization folder id
	 * @return {Promise<Array<Resource>>}
	 */
	getOrganizationFolderResources(organizationFolderId) {
		return axios.get(`/organizationFolders/${organizationFolderId}/resources`, {}).then((res) => res.data);
	},

	/**
	 * @param {number|string} organizationFolderId Organization folder id
	 * @param {{
	 *   name: string|undefined
	 *   quota: number|undefined
	 *   organizationProviderId: string|undefined
	 *   organizationId: number|undefined
	 * }} updateOrganizationFolderDto UpdateOrganizationFolderDto
	 * @param string include
	 * @return {Promise<OrganizationFolder>}
	 */
	updateOrganizationFolder(organizationFolderId, updateOrganizationFolderDto, include = "model") {
		return axios.put(`/organizationFolders/${organizationFolderId}`, { ...updateOrganizationFolderDto, include }).then((res) => res.data);
	},

	/**
	 * ADMIN ONLY
	 * 
	 * @param {{
	 *   name: string
	 *   quota: number|undefined
	 *   organizationProviderId: string|undefined
	 *   organizationId: number|undefined
	 * }} createOrganizationFolderDto CreateOrganizationFolderDto
	 * @return {Promise<OrganizationFolder>}
	 */
	createOrganizationFolder(createOrganizationFolderDto) {
		return axios.post(`/organizationFolders`, createOrganizationFolderDto).then((res) => res.data);
	},

	/* Organization Folder Member Options */

	/**
	 * Search for groups, that could be added to the organization folder as members
	 *
	 * @param {number|string} organizationFolderId Organization Folder id
	 * @param {string} search
	 * @param {number} limit
	 */
	findGroupOrganizationFolderMemberOptions(organizationFolderId, search = '', limit = 20) {
		return axios.get(`/organizationFolders/${organizationFolderId}/groupMemberOptions`, { params: { search, limit } }).then((res) => res.data);
	},

	/* Organization Folder Members */

	/**
	 * @param {number|string} organizationFolderId Organization folder id
	 * @return {Promise<Array<OrganizationFolderMember>>}
	 */
	getOrganizationFolderMembers(organizationFolderId) {
		return axios.get(`/organizationFolders/${organizationFolderId}/members`, {}).then((res) => res.data);
	},

	/**
	 * @param {number|string} organizationFolderId Organization folder id
	 * @param {{
	 *   permissionLevel: OrganizationFolderMemberPermissionLevel
	 *   principalType: PrincipalType
	 *   principalId: string
	 * }} createOrganizationFolderMemberDto CreateOrganizationFolderMemberDto
	 * @return {Promise<OrganizationFolderMember>}
	 */
	createOrganizationFolderMember(organizationFolderId, createOrganizationFolderMemberDto) {
		return axios.post(`/organizationFolders/${organizationFolderId}/members`, { ...createOrganizationFolderMemberDto }).then((res) => res.data);
	},

	/**
	 * @param {number} organizationFolderMemberId Organization folder member id
	 * @param {{
	 *   permissionLevel: OrganizationFolderMemberPermissionLevel
	 * }} updateOrganizationFolderMemberMemberDto UpdateOrganizationFolderMemberDto
	 * @return {Promise<OrganizationFolderMember>}
	 */
	updateOrganizationFolderMember(organizationFolderMemberId, updateOrganizationFolderMemberMemberDto) {
		return axios.put(`/organizationFolders/members/${organizationFolderMemberId}`, { ...updateOrganizationFolderMemberMemberDto }).then((res) => res.data);
	},

	/**
	 * @param {number} organizationFolderMemberId Organization folder member id
	 * @return {Promise<OrganizationFolderMember>}
	 */
	deleteOrganizationFolderMember(organizationFolderMemberId) {
		return axios.delete(`/organizationFolders/members/${organizationFolderMemberId}`, {}).then((res) => res.data);
	},

	/* Resources */

	/**
	 *
	 * @param {number|string} resourceId Resource id
	 * @param {string} include
	 * @return {Promise<Resource>}
	 */
	getResource(resourceId, include = "model") {
		return axios.get(`/resources/${resourceId}`, { params: { include } }).then((res) => res.data);
	},

	/**
	 *
	 * @param {number|string} resourceId Resource id
	 * @param {string} include
	 * @return {Promise<Array<Resource>>}
	 */
	getResourceSubresources(resourceId, include = "model") {
		return axios.get(`/resources/${resourceId}/subResources`, { params: { include } }).then((res) => res.data);
	},

	/**
	 * @param {number|string} resourceId Resource id
	 * @param {{
	 *   name: string|undefined
	 *   active: boolean|undefined
	 *   inheritManagers: boolean|undefined
	 *   memberPermissions: FolderRessourcePermissions|CalendarRessourcePermissions|undefined
	 *   managerPermissions: FolderRessourcePermissions|CalendarRessourcePermissions|undefined
	 *   inheritedMemberPermissions: FolderRessourcePermissions|CalendarRessourcePermissions|undefined
	 * }} updateResourceDto UpdateResourceDto
	 * @param {string} include
	 * @param {string} cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove
	 * @param {bool} cancelIfRevokesOwnManagementRights
	 * @return {Promise<Resource>}
	 */
	updateResource(resourceId, updateResourceDto, include = "model", cancelIfRevokesOwnManagementRights = true, cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove = 50) {
		return axios.put(
			`/resources/${resourceId}`,
			{
				...updateResourceDto,
				include,
				cancelIfRevokesOwnManagementRights,
				cancelIfNumberOfUsersPermissionsAddedOrDeletedAbove
			}
		).then((res) => res.data);
	},

	/**
	 * @param {number|string} resourceId Resource id
	 * @param {{
	 *   name: string
	 *   parentResourceId: number|null
	 * }} moveResourceDto MoveResourceDto
	 * @param {string} include
	 * @return {Promise<Resource>}
	 */
	moveResource(resourceId, moveResourceDto, include = "model") {
		return axios.put(
			`/resources/${resourceId}/move`,
			{
				...moveResourceDto,
				include,
			}
		).then((res) => res.data);
	},

	/**
	 * @param {{
	 * type: ResourceType
	 * organizationFolderId: number
	 * name: string
	 * parentResourceId: number|undefined
	 * active: bool
	 * inheritManagers: bool
	 *
	 * memberPermissions: FolderRessourcePermissions|CalendarRessourcePermissions
	 * managerPermissions: FolderRessourcePermissions|CalendarRessourcePermissions
	 * inheritedMemberPermissions: FolderRessourcePermissions|CalendarRessourcePermissions
	 * }} createResourceDto CreateResourceDto
	 * @param string include
	 * @return {Promise<Resource>}
	 */
	createResource(createResourceDto, include = "model") {
		return axios.post(`/resources`, { ...createResourceDto, include }).then((res) => res.data);
	},

	/**
	 * @param {number|string} resourceId Resource id
	 * @param {string} unmanagedSubfolderName name of direct subfolder
	 */
	promoteUnmanagedResourceSubfolder(resourceId, unmanagedSubfolderName) {
		return axios.post(`/resources/${resourceId}/unmanagedSubfolders/${encodeURIComponent(unmanagedSubfolderName)}/promote`).then((res) => res.data);
	},

	/**
	 * @param {number|string} resourceId Resource id
	 */
	getResourcePermissionsReport(resourceId) {
		return axios.get(`/resources/${resourceId}/permissionsReport`, {}).then((res) => res.data);
	},

	/**
	 * @param {number|string} resourceId Resource id
	 * @param string userId User id
	 */
	getResourceUserPermissionsReport(resourceId, userId) {
		return axios.get(`/resources/${resourceId}/permissionsReport/${userId}`, {}).then((res) => res.data);
	},

	
	/**
	 * Search for users, for which a permissions report could be opened
	 *
	 * @param {number|string} resourceId Resource id
	 * @param {string} search
	 * @param {number} limit
	 */
	findResourceUserPermissionsReportOptions(resourceId, search = '', limit = 20) {
		return axios.get(`/resources/${resourceId}/userPermissionsReportOptions`, { params: { search, limit } }).then((res) => res.data);
	},

	/**
	 *
	 * @param {number|string} resourceId Resource id
	 * @return {Promise<Resource>}
	 */
	deleteResource(resourceId) {
		return axios.delete(`/resources/${resourceId}`).then((res) => res.data);
	},

	/* Resource Member Options */

	/**
	 * Search for groups, that could be added to the resource as members
	 *
	 * @param {number|string} resourceId Resource id
	 * @param {string} search
	 * @param {number} limit
	 */
	findGroupResourceMemberOptions(resourceId, search = '', limit = 20) {
		return axios.get(`/resources/${resourceId}/groupMemberOptions`, { params: { search, limit } }).then((res) => res.data);
	},

	/**
	 * Search for users, that could be added to the resource as members
	 *
	 * @param {number|string} resourceId Resource id
	 * @param {string} search
	 * @param {number} limit
	 */
	findUserResourceMemberOptions(resourceId, search = '', limit = 20) {
		return axios.get(`/resources/${resourceId}/userMemberOptions`, { params: { search, limit } }).then((res) => res.data);
	},

	/* Resource Members */

	/**
	 * @param {number|string} resourceId Resource id
	 * @return {Promise<Array<ResourceMember>>}
	 */
	getResourceMembers(resourceId) {
		return axios.get(`/resources/${resourceId}/members`, {}).then((res) => res.data);
	},

	/**
	 * @param {number|string} resourceId Resource id
	 * @param {{
	 * permissionLevel: ResourceMemberPermissionLevel
	 * principalType: PrincipalType
	 * principalId: string
	 * }} createResourceMemberDto CreateResourceMemberDto
	 * @return {Promise<ResourceMember>}
	 */
	createResourceMember(resourceId, createResourceMemberDto) {
		return axios.post(`/resources/${resourceId}/members`, { ...createResourceMemberDto }).then((res) => res.data);
	},

	/**
	 * @param {number} resourceMemberId Resource member id
	 * @param {{
	 * permissionLevel: ResourceMemberPermissionLevel
	 * }} updateResourceMemberDto UpdateResourceMemberDto
	 * @param {bool} cancelIfRevokesOwnManagementRights
	 * @return {Promise<ResourceMember>}
	 */
	updateResourceMember(resourceMemberId, updateResourceMemberDto, cancelIfRevokesOwnManagementRights = true) {
		return axios.put(`/resources/members/${resourceMemberId}`, { ...updateResourceMemberDto, cancelIfRevokesOwnManagementRights }).then((res) => res.data);
	},

	/**
	 * @param {number} resourceMemberId Resource member id
	 * @param {bool} cancelIfRevokesOwnManagementRights
	 * @return {Promise<ResourceMember>}
	 */
	deleteResourceMember(resourceMemberId, cancelIfRevokesOwnManagementRights = true) {
		return axios.delete(`/resources/members/${resourceMemberId}`, { data: { cancelIfRevokesOwnManagementRights } }).then((res) => res.data);
	},

	/* Resource Link Shares */

	/**
	 * @param {number|string} resourceId Resource ID
	 * @return {Promise<Array<ResourceLinkShare>>}
	 */
	getResourceLinkShares(resourceId) {
		return axios.get(`/resources/${resourceId}/linkShares`, {}).then((res) => res.data);
	},

	/**
	 * @param {number|string} resourceId Resource ID
	 * @return {Promise<ResourceLinkShare>}
	 */
	createResourceLinkShare(resourceId) {
		return axios.post(`/resources/${resourceId}/linkShares`).then((res) => res.data);
	},

	/**
	 * @param {number|string} resourceId Resource ID
	 * @param {number} resourcelinkShareId Resource link share ID
	 * @return {Promise<ResourceLinkShare>}
	 */
	deleteResourceLinkShare(resourceId, resourceLinkShareId) {
		return axios.delete(`/resources/${resourceId}/linkShares/${resourceLinkShareId}`).then((res) => res.data);
	},

	/* Resource Snapshots */

	/**
	 * @param {number|string} resourceId Resource id
	 * @return {Promise<Array<ResourceSnapshot>>}
	 */
	getResourceSnapshots(resourceId) {
		return axios.get(`/resources/${resourceId}/snapshots`, {})
			.then((res) => (res.data.sort((a, b) => b.createdTimestamp - a.createdTimestamp)))
			.then((snapshots) => (snapshots.map((snapshot) => ({...snapshot, createdTimestamp: new Date(snapshot.createdTimestamp * 1000)}))));
	},

	/**
	 * @param {number|string} resourceId Resource id
	 * @return {Promise<Array<ResourceSnapshot>>}
	 */
	getResourceSnapshot(resourceId, snapshotId) {
		return axios.get(`/resources/${resourceId}/snapshots/${snapshotId}`, {})
			.then((res) => ({...res.data, createdTimestamp: new Date(res.data.createdTimestamp * 1000)}));
	},

	createResourceSnapshotDiff(resourceId, snapshotId, eventHandler) {
		let readerOffset = 0;

		let xhr = new XMLHttpRequest();

		xhr.open("POST", baseURL + `/resources/${resourceId}/snapshots/${snapshotId}/diff?streamed=true&includeResults=true`, true);
		
		xhr.setRequestHeader("requesttoken", getRequestToken());
		
		xhr.onprogress = (e) => {
			let response = e.currentTarget.response;

			let unparsedResponse = response.substring(readerOffset);

			let nextNewlineOffset;

			while((nextNewlineOffset = unparsedResponse.indexOf("\n")) !== -1) {
				let event = unparsedResponse.substring(0, nextNewlineOffset);

				if (event.endsWith(",")) {
					event = event.slice(0, -1);
				}

				let parsedEvent;
				try {
					parsedEvent = JSON.parse(event);
				} catch (e) {
					parsedEvent = false;
				}

				if(parsedEvent) {
					eventHandler(parsedEvent);
				}

				readerOffset += nextNewlineOffset + 1; // +1 to not include the \n of the last event
				unparsedResponse = response.substring(readerOffset);
			}
		}
		xhr.onreadystatechange = function() {
			const status = xhr.status;
			if (xhr.readyState === 4 && status !== 200) {
				eventHandler({
					status: "error",
					errorMessage: "Fehler " + status,
				});
			}
		}

		xhr.send();

		return xhr;
	},

	revertResourceSnapshotDiffItem(resourceId, snapshotId, diffTaskId, diffTaskResultId) {
		return axios.post(`/resources/${resourceId}/snapshots/${snapshotId}/diff/${diffTaskId}/${diffTaskResultId}/revert`).then((res) => res.data)
	},


	/* Organization Providers / Organizations / Organization Roles */

	/**
	 * @return {Promise<Array<OrganizationProvider>>}
	 */
	getOrganizationProviders() {
		return axios.get(`/organizationProviders`, {}).then((res) => res.data);
	},

	/**
	 * @param {string} organizationProviderId organization provider id
	 * @param {int} id organization id
	 * @return {Promise<Organization>}
	 */
	getOrganization(organizationProviderId, id) {
		return axios.get(`/organizationProviders/${organizationProviderId}/organizations/${id}`, { }).then((res) => res.data);
	},

	/**
	 * @param {string} organizationProviderId organization provider id
	 * @param {number|undefined} parentOrganizationId parent organization id (null if top level organizations)
	 * @return {Promise<Array<Organization>>}
	 */
	getSubOrganizations(organizationProviderId, parentOrganizationId) {
		if(parentOrganizationId) {
			return axios.get(`/organizationProviders/${organizationProviderId}/organizations/${parentOrganizationId}/subOrganizations`, { }).then((res) => res.data);
		} else {
			return axios.get(`/organizationProviders/${organizationProviderId}/subOrganizations`, { }).then((res) => res.data);
		}

	},

	/**
	 * @param {string} organizationProviderId organization provider id
	 * @param {number} organizationId organization id
	 * @return {Promise<Array<Organization>>}
	 */
	getRoles(organizationProviderId, organizationId) {
		return axios.get(`/organizationProviders/${organizationProviderId}/organizations/${organizationId}/roles/`, { }).then((res) => res.data);
	}
}
