import axios from "@nextcloud/axios"
import { generateUrl } from "@nextcloud/router"

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
 * type: number,
 * id: string,
 * }} Principal
 * 
 * @typedef {{
 * id: number
 * resourceId: number
 * permissionLevel: number
 * principal: Principal,
 * createdTimestamp: number,
 * lastUpdatedTimestamp: number,
 * }} ResourceMember
 * 
 */

axios.defaults.baseURL = generateUrl("/apps/organization_folders")

export default {
	/**
	 *
	 * @param {number|string} resourceId Resource id
	 * @param {string} include 
	 * @return {Promise<Resource>}
	 */
	getResource(resourceId, include = "model") {
		return axios.get(`/resources/${resourceId}`, { params: { include } }).then((res) => res.data)
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
	updateResource(resourceId, updateGroupDto) {
		return axios.put(`/resources/${resourceId}`, { ...updateGroupDto }).then((res) => res.data)
	},

	/**
	 *
	 * @param {number|string} resourceId Resource id
	 * @return {Promise<Array<ResourceMember>>}
	 */
	getResourceMembers(resourceId) {
		return axios.get(`/resources/${resourceId}/members`, {}).then((res) => res.data)
	},
}
