import { defineStore } from "pinia";
import api from "../api.js";

export const useCurrentDirStore = defineStore("currentDir", {
	state: () => ({
		loading: false,
		path: "",
		organizationFolderId: null,
		organizationFolderUpdatePermissions: null,
		organizationFolderReadLimitedPermissions: null,
		organizationFolderResourceId: null,
		organizationFolderResourceUpdatePermissions: null,
  	}),
	actions: {
		/**
		 * @param {string} path path of new directory
		 * @param attributes DAV attributes of directory
		 */
		async update(path, attributes) {
			this.path = path

			this.organizationFolderId = attributes["organization-folder-id"];
			this.organizationFolderUpdatePermissions = attributes["organization-folder-user-has-update-permissions"];
			this.organizationFolderReadLimitedPermissions = attributes["organization-folder-user-has-read-limited-permissions"];
			this.organizationFolderResourceId = attributes["organization-folder-resource-id"];
			this.organizationFolderResourceUpdatePermissions = attributes["organization-folder-resource-user-has-update-permissions"];
		},

		async fetchCurrentResource() {
			if(this.organizationFolderResourceId) {
				return await api.getResource(this.organizationFolderResourceId);
			} else {
				return false;
			}
		}
	},
})
