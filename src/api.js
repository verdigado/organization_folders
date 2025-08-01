import axios from "@nextcloud/axios";
import { getRequestToken } from '@nextcloud/auth';
import { generateUrl } from "@nextcloud/router";
import { showError } from "@nextcloud/dialogs";

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
 * id: number
 * type: ResourceType
 * organizationFolderId: number
 * name: string
 * parentResource: number
 * active: bool
 * inheritManagers: bool
 * createdTimestamp: number
 * lastUpdatedTimestamp: number
 * membersAclPermission: number
 * managersAclPermission: number
 * inheritedAclPermission: number
 * members: Array<ResourceMember>|undefined
 * subResources: Array<Resource>|undefined
 * }} FolderResource
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
 * friendlyName: string
 * fullHierarchyNames: string[]
 * }} Principal
 *
 * @typedef {{
 * id: number
 * resourceId: number
 * permissionLevel: ResourceMemberPermissionLevel
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
 */

const baseURL = generateUrl("/apps/organization_folders");
axios.defaults.baseURL = baseURL;

axios.interceptors.response.use(r => r, function (error) {
	showError(error.response?.data?.message);
	return Promise.reject(error);
});

export default {
	PrincipalTypes,
	OrganizationFolderMemberPermissionLevels,
	ResourceMemberPermissionLevels,
	ResourceTypes,

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
	 * @param {number|string} resourceId Resource id
	 * @param {{
	 *   name: string|undefined
	 *   active: boolean|undefined
	 *   inheritManagers: boolean|undefined
	 *   membersAclPermission: number|undefined
	 *   managersAclPermission: number|undefined
	 *   inheritedAclPermission: number|undefined
	 * }} updateResourceDto UpdateResourceDto
	 * @param string include
	 * @return {Promise<Resource>}
	 */
	updateResource(resourceId, updateResourceDto, include = "model") {
		return axios.put(`/resources/${resourceId}`, { ...updateResourceDto, include }).then((res) => res.data);
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
	 * membersAclPermission: number|undefined
	 * managersAclPermission: number|undefined
	 * inheritedAclPermission: number|undefined
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
	 * @return {Promise<ResourceMember>}
	 */
	updateResourceMember(resourceMemberId, updateResourceMemberDto) {
		return axios.put(`/resources/members/${resourceMemberId}`, { ...updateResourceMemberDto }).then((res) => res.data);
	},

	/**
	 * @param {number} resourceMemberId Resource member id
	 * @return {Promise<ResourceMember>}
	 */
	deleteResourceMember(resourceMemberId) {
		return axios.delete(`/resources/members/${resourceMemberId}`, {}).then((res) => res.data);
	},

	/* Resource Snapshots */

	/**
	 * @param {number|string} resourceId Resource id
	 * @return {Promise<Array<ResourceSnapshot>>}
	 */
	getResourceSnapshots(resourceId) {
		return axios.get(`/resources/${resourceId}/snapshots`, {})
			.then((res) => (res.data.map((snapshot) => ({...snapshot, createdTimestamp: new Date(snapshot.createdTimestamp * 1000)}))));
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
