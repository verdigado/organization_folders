import axios from "@nextcloud/axios"
import { generateUrl } from "@nextcloud/router"

/**
 * @typedef {number} PrincipalType
 **/

/**
 * @enum {PrincipalType}
 */
var PrincipalTypes = {
    USER: 1,
    GROUP: 2,
	ROLE: 3,
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
 * id: number
 * type: string
 * organizationFolderId: number
 * name: string
 * parentResource: number
 * active: bool
 * inheritManagers: bool
 * membersAclPermission: number
 * managersAclPermission: number
 * inheritedAclPermission: number
 * }} FolderResource
 * 
 * @typedef {(FolderResource)} Resource
 * 
 * @typedef {{
 * type: PrincipalType,
 * id: string,
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

axios.defaults.baseURL = generateUrl("/apps/organization_folders")

export default {
	PrincipalTypes,
	ResourceMemberPermissionLevels,

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
	 * @return {Promise<Resource>}
	 */
	updateResource(resourceId, updateGroupDto, include = "model") {
		return axios.put(`/resources/${resourceId}`, { ...updateGroupDto, include }).then((res) => res.data);
	},

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

	/**
	 * @return {Promise<Array<string>>}
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
