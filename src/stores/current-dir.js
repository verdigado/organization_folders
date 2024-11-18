import { defineStore } from "pinia";
import { computed } from "vue";
import { getFolderProperties } from "../davClient.js";
import api from "../api.js";

export const useCurrentDirStore = defineStore("currentDir", {
	state: () => ({
		loading: false,
		path: "",
		organizationFolderId: null,
		organizationFolderResourceId: null,
		userManagerPermissions: null,
  	}),
	actions: {
		/**
		 * set the path of the current directory and fetch organization folders info from dav api
		 *
		 * @param {string} path current path
		 */
		async updatePath(path) {
			this.loading = true;
			this.path = path

			let { fileInfo } = await getFolderProperties(path)
				.catch(() => {
					this.organizationFolderId = false;
					this.organizationFolderResourceId = false;
					this.userManagerPermissions = false;
					this.loading = false;
				});

			console.log("fileInfo", fileInfo);

			if(fileInfo) {
				this.organizationFolderId = fileInfo.organizationFolderId;
				this.organizationFolderResourceId = fileInfo.organizationFolderResourceId;
				this.userManagerPermissions = fileInfo.userManagerPermissions;
			} else {
				this.organizationFolderId = false;
				this.organizationFolderResourceId = false;
				this.userManagerPermissions = false;
			}

			this.loading = false;
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
